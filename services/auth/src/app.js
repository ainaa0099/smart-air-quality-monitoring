const express = require("express");
const cors = require("cors");
const jwt = require("jsonwebtoken");
const bcryptjs = require("bcryptjs");
const crypto = require("crypto");
const db = require("./config/database");
require("dotenv").config();

const app = express();
const PORT = process.env.PORT || 3002;

// ==================== CONFIGURATION ====================

const JWT_ACCESS_SECRET = process.env.JWT_ACCESS_SECRET || "access-secret-key";
const JWT_ACCESS_EXPIRES = process.env.JWT_ACCESS_EXPIRES || "15m";
const JWT_REFRESH_SECRET =
  process.env.JWT_REFRESH_SECRET || "refresh-secret-key";
const JWT_REFRESH_EXPIRES = process.env.JWT_REFRESH_EXPIRES || "7d";

// ==================== MIDDLEWARE ====================

app.use(
  cors({
    origin: process.env.CORS_ORIGIN || "*",
    credentials: true,
  }),
);

app.use(express.json());
app.use(express.urlencoded({ extended: true }));

// ==================== UTILITIES ====================

/**
 * Generate Access Token
 */
const generateAccessToken = (user, clientId = null) => {
  const payload = {
    sub: user.id,
    email: user.email,
    name: user.name,
    role: user.role || "user",
    client_id: clientId,
  };

  return jwt.sign(payload, JWT_ACCESS_SECRET, {
    expiresIn: JWT_ACCESS_EXPIRES,
    issuer: "oauth-server",
    audience: "api-gateway",
  });
};

/**
 * Generate Refresh Token
 */
const generateRefreshToken = (userId) => {
  return jwt.sign({ sub: userId }, JWT_REFRESH_SECRET, {
    expiresIn: JWT_REFRESH_EXPIRES,
    issuer: "oauth-server",
  });
};

/**
 * Generate Authorization Code
 */
const generateAuthorizationCode = () => {
  return crypto.randomBytes(32).toString("hex");
};

/**
 * Hash password
 */
const hashPassword = async (password) => {
  return bcryptjs.hash(password, 10);
};

/**
 * Verify password
 */
const verifyPassword = async (password, hash) => {
  return bcryptjs.compare(password, hash);
};

/**
 * Validate client credentials
 */
const validateClient = async (clientId, clientSecret) => {
  try {
    const connection = await db.getConnection();
    const [clients] = await connection.query(
      "SELECT * FROM oauth_clients WHERE client_id = ? AND is_active = TRUE",
      [clientId],
    );
    connection.release();

    if (clients.length === 0) return null;

    const client = clients[0];
    const isValidSecret = await bcryptjs.compare(
      clientSecret,
      client.client_secret,
    );
    return isValidSecret ? client : null;
  } catch (error) {
    console.error("Client validation error:", error);
    return null;
  }
};

// ==================== ERROR RESPONSE ====================

const errorResponse = (res, code, status, message) => {
  res.status(code).json({
    status: status,
    code: code,
    message: message,
    timestamp: new Date().toISOString(),
    service: "auth",
  });
};

// ==================== HEALTH CHECK ====================

app.get("/health", async (req, res) => {
  try {
    const connection = await db.getConnection();
    await connection.query("SELECT 1");
    connection.release();

    res.status(200).json({
      status: "success",
      code: 200,
      message: "Auth service is healthy",
      data: {
        database: "connected",
        service: "oauth2-authorization-server",
      },
      timestamp: new Date().toISOString(),
      service: "auth",
    });
  } catch (error) {
    console.error("Health check error:", error);
    res.status(503).json({
      status: "error",
      code: 503,
      message: "Database connection failed",
      timestamp: new Date().toISOString(),
      service: "auth",
    });
  }
});

// ==================== OAUTH 2.0 TOKEN ENDPOINT ====================

/**
 * POST /oauth/token
 * OAuth 2.0 Token Endpoint
 * Supports: password, client_credentials, refresh_token grant types
 */
app.post("/oauth/token", async (req, res) => {
  const {
    grant_type,
    username,
    password,
    client_id,
    client_secret,
    refresh_token,
    scope,
  } = req.body;

  if (!grant_type) {
    return errorResponse(res, 400, "error", "grant_type parameter is required");
  }

  try {
    // =============== PASSWORD GRANT ===============
    if (grant_type === "password") {
      if (!username || !password) {
        return errorResponse(
          res,
          400,
          "error",
          "username and password are required",
        );
      }

      const connection = await db.getConnection();

      // Find user
      const [users] = await connection.query(
        "SELECT * FROM users WHERE email = ? AND is_active = TRUE",
        [username],
      );

      if (users.length === 0) {
        connection.release();
        return errorResponse(res, 401, "error", "Invalid credentials");
      }

      const user = users[0];

      // Verify password
      const isPasswordValid = await verifyPassword(password, user.password);
      if (!isPasswordValid) {
        connection.release();
        return errorResponse(res, 401, "error", "Invalid credentials");
      }

      // Generate tokens
      const accessToken = generateAccessToken(user, client_id);
      const refreshTokenValue = generateRefreshToken(user.id);

      // Save refresh token
      const expiresAt = new Date();
      expiresAt.setDate(expiresAt.getDate() + 7);

      await connection.query(
        "INSERT INTO refresh_tokens (user_id, token, expires_at) VALUES (?, ?, ?)",
        [user.id, refreshTokenValue, expiresAt],
      );

      connection.release();

      return res.status(200).json({
        status: "success",
        code: 200,
        data: {
          access_token: accessToken,
          token_type: "Bearer",
          expires_in: 900, // 15 minutes
          refresh_token: refreshTokenValue,
          user: {
            id: user.id,
            email: user.email,
            name: user.name,
          },
        },
        message: "Token issued successfully",
        timestamp: new Date().toISOString(),
        service: "auth",
      });
    }

    // =============== CLIENT CREDENTIALS GRANT ===============
    else if (grant_type === "client_credentials") {
      if (!client_id || !client_secret) {
        return errorResponse(
          res,
          400,
          "error",
          "client_id and client_secret are required",
        );
      }

      const client = await validateClient(client_id, client_secret);
      if (!client) {
        return errorResponse(res, 401, "error", "Invalid client credentials");
      }

      // Check if client supports this grant type
      const grantTypes = JSON.parse(client.grant_types || "[]");
      if (!grantTypes.includes("client_credentials")) {
        return errorResponse(
          res,
          400,
          "error",
          "Client does not support client_credentials grant",
        );
      }

      // Generate access token (no user)
      const accessToken = jwt.sign(
        {
          sub: client.client_id,
          client_id: client.client_id,
          client_name: client.client_name,
          scope: scope || "default",
        },
        JWT_ACCESS_SECRET,
        {
          expiresIn: JWT_ACCESS_EXPIRES,
          issuer: "oauth-server",
        },
      );

      return res.status(200).json({
        status: "success",
        code: 200,
        data: {
          access_token: accessToken,
          token_type: "Bearer",
          expires_in: 900,
          scope: scope || "default",
        },
        message: "Client token issued successfully",
        timestamp: new Date().toISOString(),
        service: "auth",
      });
    }

    // =============== REFRESH TOKEN GRANT ===============
    else if (grant_type === "refresh_token") {
      if (!refresh_token) {
        return errorResponse(res, 400, "error", "refresh_token is required");
      }

      const connection = await db.getConnection();

      // Find refresh token
      const [tokens] = await connection.query(
        "SELECT rt.*, u.* FROM refresh_tokens rt JOIN users u ON rt.user_id = u.id WHERE rt.token = ? AND rt.is_revoked = FALSE AND rt.expires_at > NOW()",
        [refresh_token],
      );

      if (tokens.length === 0) {
        connection.release();
        return errorResponse(
          res,
          401,
          "error",
          "Invalid or expired refresh token",
        );
      }

      const token = tokens[0];

      // Generate new access token
      const newAccessToken = generateAccessToken({
        id: token.user_id,
        email: token.email,
        name: token.name,
        role: token.role,
      });

      // Optionally generate new refresh token
      const newRefreshToken = generateRefreshToken(token.user_id);

      // Invalidate old refresh token
      await connection.query(
        "UPDATE refresh_tokens SET is_revoked = TRUE WHERE id = ?",
        [token.id],
      );

      // Save new refresh token
      const expiresAt = new Date();
      expiresAt.setDate(expiresAt.getDate() + 7);

      await connection.query(
        "INSERT INTO refresh_tokens (user_id, token, expires_at) VALUES (?, ?, ?)",
        [token.user_id, newRefreshToken, expiresAt],
      );

      connection.release();

      return res.status(200).json({
        status: "success",
        code: 200,
        data: {
          access_token: newAccessToken,
          token_type: "Bearer",
          expires_in: 900,
          refresh_token: newRefreshToken,
        },
        message: "Token refreshed successfully",
        timestamp: new Date().toISOString(),
        service: "auth",
      });
    } else {
      return errorResponse(
        res,
        400,
        "error",
        "Unsupported grant type: " + grant_type,
      );
    }
  } catch (error) {
    console.error("Token endpoint error:", error);
    errorResponse(res, 500, "error", "Token generation failed");
  }
});

// ==================== OAUTH 2.0 INTROSPECTION ENDPOINT ====================

/**
 * POST /oauth/introspect
 * Token Introspection Endpoint
 * Returns token validity and metadata
 */
app.post("/oauth/introspect", async (req, res) => {
  const { token } = req.body;

  if (!token) {
    return res.status(400).json({
      active: false,
      error: "token parameter is required",
    });
  }

  try {
    const decoded = jwt.verify(token, JWT_ACCESS_SECRET);

    // Check if token is in blacklist
    const connection = await db.getConnection();
    const [blacklisted] = await connection.query(
      "SELECT * FROM oauth_tokens WHERE access_token = ? AND is_revoked = TRUE",
      [token],
    );
    connection.release();

    if (blacklisted.length > 0) {
      return res.status(200).json({
        active: false,
        revoked: true,
        error: "Token has been revoked",
      });
    }

    return res.status(200).json({
      active: true,
      sub: decoded.sub,
      email: decoded.email,
      name: decoded.name,
      role: decoded.role,
      client_id: decoded.client_id,
      scope: decoded.scope,
      iat: Math.floor(new Date(decoded.iat * 1000).getTime() / 1000),
      exp: decoded.exp,
      expires_in: Math.max(0, decoded.exp - Math.floor(Date.now() / 1000)),
    });
  } catch (error) {
    // Token is invalid or expired
    return res.status(200).json({
      active: false,
      error: error.message,
    });
  }
});

// ==================== OAUTH 2.0 REVOCATION ENDPOINT ====================

/**
 * POST /oauth/revoke
 * Token Revocation Endpoint
 */
app.post("/oauth/revoke", async (req, res) => {
  const { token } = req.body;

  if (!token) {
    return errorResponse(res, 400, "error", "token parameter is required");
  }

  try {
    const connection = await db.getConnection();

    // Mark token as revoked in database
    await connection.query(
      "INSERT INTO oauth_tokens (token_id, client_id, access_token, token_type, expires_at, is_revoked) VALUES (?, ?, ?, ?, NOW(), TRUE)",
      [crypto.randomBytes(16).toString("hex"), "unknown", token, "Bearer"],
    );

    // Also mark refresh tokens as revoked if this is a user token
    try {
      const decoded = jwt.verify(token, JWT_ACCESS_SECRET);
      if (decoded.sub && !token.includes("client")) {
        await connection.query(
          "UPDATE refresh_tokens SET is_revoked = TRUE WHERE user_id = ? AND token = ?",
          [decoded.sub, token],
        );
      }
    } catch (e) {
      // Token already invalid, continue
    }

    connection.release();

    return res.status(200).json({
      status: "success",
      code: 200,
      message: "Token revoked successfully",
      timestamp: new Date().toISOString(),
      service: "auth",
    });
  } catch (error) {
    console.error("Revocation error:", error);
    errorResponse(res, 500, "error", "Token revocation failed");
  }
});

// ==================== USER ENDPOINTS ====================

/**
 * POST /register
 * User Registration
 */
app.post("/register", async (req, res) => {
  const { name, email, password } = req.body;

  if (!name || !email || !password) {
    return errorResponse(
      res,
      422,
      "error",
      "name, email, and password are required",
    );
  }

  if (password.length < 6) {
    return errorResponse(
      res,
      422,
      "error",
      "Password must be at least 6 characters",
    );
  }

  try {
    const connection = await db.getConnection();

    // Check if email exists
    const [existing] = await connection.query(
      "SELECT id FROM users WHERE email = ?",
      [email],
    );

    if (existing.length > 0) {
      connection.release();
      return errorResponse(res, 409, "error", "Email already registered");
    }

    // Hash password and create user
    const hashedPassword = await hashPassword(password);
    const [result] = await connection.query(
      "INSERT INTO users (name, email, password, oauth_provider, is_active) VALUES (?, ?, ?, ?, TRUE)",
      [name, email, hashedPassword, "local"],
    );

    const userId = result.insertId;

    // Generate tokens
    const user = { id: userId, email, name, role: "user" };
    const accessToken = generateAccessToken(user);
    const refreshToken = generateRefreshToken(userId);

    // Save refresh token
    const expiresAt = new Date();
    expiresAt.setDate(expiresAt.getDate() + 7);

    await connection.query(
      "INSERT INTO refresh_tokens (user_id, token, expires_at) VALUES (?, ?, ?)",
      [userId, refreshToken, expiresAt],
    );

    connection.release();

    return res.status(201).json({
      status: "success",
      code: 201,
      data: {
        user: {
          id: userId,
          name,
          email,
        },
        access_token: accessToken,
        refresh_token: refreshToken,
        token_type: "Bearer",
      },
      message: "User registered successfully",
      timestamp: new Date().toISOString(),
      service: "auth",
    });
  } catch (error) {
    console.error("Registration error:", error);
    errorResponse(res, 500, "error", "Registration failed");
  }
});

/**
 * POST /login
 * User Login
 */
app.post("/login", async (req, res) => {
  const { email, password } = req.body;

  if (!email || !password) {
    return errorResponse(res, 422, "error", "email and password are required");
  }

  try {
    const connection = await db.getConnection();

    const [users] = await connection.query(
      "SELECT * FROM users WHERE email = ? AND is_active = TRUE",
      [email],
    );

    if (users.length === 0) {
      connection.release();
      return errorResponse(res, 401, "error", "Invalid credentials");
    }

    const user = users[0];
    const isValidPassword = await verifyPassword(password, user.password);

    if (!isValidPassword) {
      connection.release();
      return errorResponse(res, 401, "error", "Invalid credentials");
    }

    // Generate tokens
    const accessToken = generateAccessToken(user);
    const refreshToken = generateRefreshToken(user.id);

    // Save refresh token
    const expiresAt = new Date();
    expiresAt.setDate(expiresAt.getDate() + 7);

    await connection.query(
      "INSERT INTO refresh_tokens (user_id, token, expires_at) VALUES (?, ?, ?)",
      [user.id, refreshToken, expiresAt],
    );

    connection.release();

    return res.status(200).json({
      status: "success",
      code: 200,
      data: {
        user: {
          id: user.id,
          name: user.name,
          email: user.email,
          role: user.role,
        },
        access_token: accessToken,
        refresh_token: refreshToken,
        token_type: "Bearer",
      },
      message: "Login successful",
      timestamp: new Date().toISOString(),
      service: "auth",
    });
  } catch (error) {
    console.error("Login error:", error);
    errorResponse(res, 500, "error", "Login failed");
  }
});

/**
 * POST /social-login
 * Endpoint for Google, Facebook, and GitHub Login (Mock/Skeleton)
 */
app.post("/social-login", async (req, res) => {
  const { provider, token, email, name } = req.body;

  if (!provider || !["google", "facebook", "github"].includes(provider)) {
    return errorResponse(
      res,
      400,
      "error",
      "Valid provider (google, facebook, github) is required",
    );
  }

  if (!token) {
    return errorResponse(res, 400, "error", "Social token is required");
  }

  // Mengembalikan response mock untuk keperluan testing endpoint:
  return res.status(200).json({
    status: "success",
    code: 200,
    data: {
      user: {
        name: name || `User ${provider}`,
        email: email || `user@${provider}.com`,
        oauth_provider: provider,
      },
      access_token: "MOCK_JWT_UNTUK_SOCIAL_LOGIN",
      message: `Berhasil hit endpoint social login. Silakan implementasikan validasi token API ${provider} sesungguhnya.`,
    },
    timestamp: new Date().toISOString(),
    service: "auth",
  });
});

// ==================== ERROR HANDLERS ====================

app.use((req, res) => {
  errorResponse(res, 404, "error", "Route not found");
});

app.use((err, req, res, next) => {
  console.error("Auth service error:", err);
  errorResponse(res, 500, "error", "Internal server error");
});

// ==================== SERVER START ====================

app.listen(PORT, () => {
  console.log(`
╔══════════════════════════════════════╗
║   OAuth 2.0 Authorization Server     ║
╚══════════════════════════════════════╝

  Service running on: http://localhost:${PORT}
  Environment: ${process.env.NODE_ENV || "development"}

  Endpoints:
  - POST   /oauth/token        (Token generation)
  - POST   /oauth/introspect   (Token validation)
  - POST   /oauth/revoke       (Token revocation)
  - POST   /register           (User registration)
  - POST   /login              (User login)
  - GET    /health             (Health check)

  Grant Types Supported:
  - password
  - client_credentials
  - refresh_token
  `);
});

module.exports = app;
