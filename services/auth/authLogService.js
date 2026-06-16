const { getLocationFromIp } = require("./locationService");
const { createLoginLog } = require("./loginLogService");

const recordLoginLocation = async (
  req,
  userId,
  loginMethod,
  provider = null,
) => {
  try {
    let ip = req.ip || req.connection.remoteAddress;

    if (ip && ip.startsWith("::ffff:")) {
      ip = ip.substring(7);
    }

    const locationData = await getLocationFromIp(ip);

    const logData = {
      user_id: userId,
      login_method: loginMethod,
      provider: provider,
      ip_address: ip,
      user_agent: req.headers["user-agent"] || null,
      city: locationData ? locationData.city : null,
      region: locationData ? locationData.region : null,
      country: locationData ? locationData.country : null,
      loc: locationData ? locationData.loc : null,
      timezone: locationData ? locationData.timezone : null,
      org: locationData ? locationData.org : null,
    };

    // Simpan ke database
    await createLoginLog(logData);
  } catch (error) {
    console.error(
      "[Auth Log Service] Failed to record login location:",
      error.message,
    );
    // Gagal menyimpan log tidak boleh memblokir login user
  }
};

module.exports = { recordLoginLocation };
