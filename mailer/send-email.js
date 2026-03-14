const nodemailer = require("nodemailer");

function fail(message) {
  console.error(message);
  process.exit(1);
}

async function main() {
  const encoded = process.argv[2];
  if (!encoded) {
    fail("Missing payload argument.");
  }

  let payload;
  try {
    payload = JSON.parse(Buffer.from(encoded, "base64").toString("utf8"));
  } catch (error) {
    fail(`Invalid payload: ${error.message}`);
  }

  const smtp = payload?.smtp || {};
  const mail = payload?.mail || {};

  if (!smtp.host || !smtp.port || !smtp.user || !smtp.pass || !smtp.fromEmail) {
    fail("SMTP config is incomplete.");
  }
  if (!mail.to || !mail.subject || !mail.text) {
    fail("Email fields are incomplete.");
  }

  const transporter = nodemailer.createTransport({
    host: smtp.host,
    port: Number(smtp.port),
    secure: Boolean(smtp.secure),
    auth: {
      user: smtp.user,
      pass: smtp.pass,
    },
  });

  await transporter.sendMail({
    from: smtp.fromName ? `"${smtp.fromName}" <${smtp.fromEmail}>` : smtp.fromEmail,
    to: mail.to,
    subject: mail.subject,
    text: mail.text,
  });
}

main().catch((error) => {
  fail(`Send failed: ${error.message}`);
});
