const axios = require("axios");

const getLocationFromIp = async (ip) => {
  try {
    const token = process.env.IPINFO_TOKEN;
    if (!token) {
      console.warn("IPINFO_TOKEN is not configured in .env");
      return null;
    }

    const url = `https://ipinfo.io/${ip}?token=${token}`;
    const response = await axios.get(url, { timeout: 3000 }); // timeout 3 detik

    return response.data;
  } catch (error) {
    // Log pesan error ke terminal untuk keperluan debugging server
    if (error.response) {
      console.error(
        "[Location Service] IPinfo API Error:",
        error.response.status,
        error.response.data,
      );
    } else {
      console.error("[Location Service] Request Failed:", error.message);
    }
    return null;
  }
};

module.exports = { getLocationFromIp };
