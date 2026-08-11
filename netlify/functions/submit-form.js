const Busboy = require('busboy');
const nodemailer = require('nodemailer');

const MAX_ATTACHMENT_SIZE = 8 * 1024 * 1024;

const forms = {
  'vendor-partnership': {
    to: 'partners@thekcsoft.com',
    subject: 'Vendor Partnership Inquiry',
    required: ['first-name', 'last-name', 'company', 'email', 'partnership-type', 'message', 'privacy-acknowledgement']
  },
  'submit-requirement': {
    to: 'vendors@thekcsoft.com',
    cc: 'partners@thekcsoft.com',
    subject: 'New Staffing Requirement',
    required: ['company', 'contact-name', 'email', 'job-title-skill', 'job-description', 'privacy-acknowledgement'],
    file: 'attachment',
    allowed: ['pdf', 'doc', 'docx', 'txt']
  },
  'talent-network': {
    to: 'recruiting@thekcsoft.com',
    subject: 'New Talent Network Submission',
    required: ['full-name', 'email', 'primary-skill', 'privacy-acknowledgement'],
    file: 'resume',
    fileRequired: true,
    allowed: ['pdf', 'doc', 'docx']
  },
  contact: {
    to: 'info@thekcsoft.com',
    subject: 'Website Contact Inquiry',
    required: ['name', 'email', 'message', 'privacy-acknowledgement']
  }
};

function response(statusCode, payload) {
  return {
    statusCode,
    headers: {
      'Content-Type': 'application/json',
      'X-Content-Type-Options': 'nosniff'
    },
    body: JSON.stringify(payload)
  };
}

function clean(value) {
  return String(value || '').replace(/\r|\0/g, '').trim();
}

function label(name) {
  return name.replace(/-/g, ' ').replace(/\b\w/g, char => char.toUpperCase());
}

function safeFilename(name) {
  return String(name || 'attachment').split(/[\\/]/).pop().replace(/[^A-Za-z0-9._-]/g, '_');
}

function parseMultipart(event) {
  return new Promise((resolve, reject) => {
    const fields = {};
    const files = {};
    const contentType = event.headers['content-type'] || event.headers['Content-Type'];
    const busboy = Busboy({headers: {'content-type': contentType}});
    const body = Buffer.from(event.body || '', event.isBase64Encoded ? 'base64' : 'utf8');

    busboy.on('field', (name, value) => {
      fields[name] = clean(value);
    });
    busboy.on('file', (name, file, info) => {
      const chunks = [];
      let size = 0;
      file.on('data', chunk => {
        size += chunk.length;
        chunks.push(chunk);
      });
      file.on('end', () => {
        if (info.filename) {
          files[name] = {
            filename: safeFilename(info.filename),
            contentType: info.mimeType || 'application/octet-stream',
            size,
            content: Buffer.concat(chunks)
          };
        }
      });
    });
    busboy.on('error', reject);
    busboy.on('finish', () => resolve({fields, files}));
    busboy.end(body);
  });
}

function parseUrlEncoded(event) {
  const params = new URLSearchParams(event.body || '');
  const fields = {};
  for (const [key, value] of params.entries()) {
    fields[key] = clean(value);
  }
  return {fields, files: {}};
}

function createTransport() {
  const {SMTP_HOST, SMTP_PORT, SMTP_USER, SMTP_PASS, SMTP_SECURE} = process.env;
  if (!SMTP_HOST || !SMTP_PORT || !SMTP_USER || !SMTP_PASS) {
    throw new Error('SMTP is not configured.');
  }
  return nodemailer.createTransport({
    host: SMTP_HOST,
    port: Number(SMTP_PORT),
    secure: SMTP_SECURE === 'true' || Number(SMTP_PORT) === 465,
    auth: {
      user: SMTP_USER,
      pass: SMTP_PASS
    }
  });
}

exports.handler = async event => {
  if (event.httpMethod !== 'POST') {
    return response(405, {success: false, message: 'Method not allowed.'});
  }

  try {
    const contentType = event.headers['content-type'] || event.headers['Content-Type'] || '';
    const parsed = contentType.includes('multipart/form-data')
      ? await parseMultipart(event)
      : parseUrlEncoded(event);
    const {fields, files} = parsed;

    if (fields['bot-field']) {
      return response(200, {success: true});
    }

    const formName = fields['form-name'];
    const config = forms[formName];
    if (!config) {
      return response(400, {success: false, message: 'Unknown form.'});
    }

    for (const required of config.required) {
      if (!fields[required]) {
        return response(422, {success: false, message: `${label(required)} is required.`});
      }
    }

    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(fields.email || '')) {
      return response(422, {success: false, message: 'A valid email address is required.'});
    }

    const attachments = [];
    if (config.file) {
      const upload = files[config.file];
      if (!upload && config.fileRequired) {
        return response(422, {success: false, message: 'Resume upload is required.'});
      }
      if (upload) {
        const extension = upload.filename.split('.').pop().toLowerCase();
        if (!config.allowed.includes(extension) || upload.size > MAX_ATTACHMENT_SIZE) {
          return response(422, {success: false, message: 'Unsupported attachment type or size.'});
        }
        attachments.push({
          filename: upload.filename,
          content: upload.content,
          contentType: upload.contentType
        });
      }
    }

    const text = Object.entries(fields)
      .filter(([key]) => !['form-name', 'bot-field', 'privacy-acknowledgement'].includes(key))
      .map(([key, value]) => `${label(key)}: ${value}`)
      .join('\n');

    const transporter = createTransport();
    await transporter.sendMail({
      from: process.env.MAIL_FROM || 'Kairos Covenant Website <info@thekcsoft.com>',
      to: config.to,
      cc: config.cc,
      bcc: process.env.MAIL_BCC || process.env.SMTP_USER,
      replyTo: fields.email,
      subject: `${config.subject} - thekcsoft.com`,
      text,
      attachments
    });

    return response(200, {success: true});
  } catch (error) {
    return response(500, {success: false, message: 'Submission could not be delivered.'});
  }
};
