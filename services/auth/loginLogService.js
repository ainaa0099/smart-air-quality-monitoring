const db = require("./src/config/database.js");

const createLoginLog = async (data) => {
  let connection;
  try {
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

    connection = await db.getConnection();
    await connection.query(query, values);
  } catch (error) {
    console.error("Error creating login log:", error);
  } finally {
    if (connection) {
      connection.release();
    }
  }
};

module.exports = { createLoginLog };
