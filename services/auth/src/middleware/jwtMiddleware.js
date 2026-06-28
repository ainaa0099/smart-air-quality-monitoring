const jwt = require("jsonwebtoken");

// Token Blacklist (dalam memory - untuk production gunakan Redis)
const tokenBlacklist = new Set();

// JWT Verification Middleware
const verifyJWT = (req, res, next) => {
  const authHeader = req.headers.authorization;
  const token = authHeader?.split(" ")[1];

  if (!token) {
    return res.status(401).json({
      status: "error",
      message: "No token provided",
    });
  }

  // Check if token is blacklisted
  if (tokenBlacklist.has(token)) {
    return res.status(401).json({
      status: "error",
      message: "Token has been invalidated (logged out)",
    });
  }

  try {
    const decoded = jwt.verify(token, process.env.JWT_ACCESS_SECRET);
    req.user = decoded;
    next();
  } catch (error) {
    return res.status(401).json({
      status: "error",
      message: "Invalid or expired token",
    });
  }
};

// Add token to blacklist
const addToBlacklist = (token) => {
  tokenBlacklist.add(token);
};

// Get blacklist
const getBlacklist = () => {
  return tokenBlacklist;
};

module.exports = {
  verifyJWT,
  addToBlacklist,
  getBlacklist,
  tokenBlacklist,
};
