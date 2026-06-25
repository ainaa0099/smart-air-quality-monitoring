const db = require("./src/config/database");

const createLoginLog = async (data) => {
  const query = `
    INSERT INTO login_logs 
    (user_id, login_method, provider, ip_address, city, region, country, loc, timezone, org, user_agent) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
  `;

  const values = [
    data.user_id,
    data.login_method,
    data.provider,
    data.ip_address,
    data.city || null,
    data.region || null,
    data.country || null,
    data.loc || null,
    data.timezone || null,
    data.org || null,
    data.user_agent || null,
  ];

  const connection = await db.getConnection();
  await connection.query(query, values);
  connection.release();
};

module.exports = { createLoginLog };
