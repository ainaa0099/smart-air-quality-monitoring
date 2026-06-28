const express = require("express");
const cors = require("cors");
const jwt = require("jsonwebtoken");
const bcryptjs = require("bcryptjs");
const crypto = require("crypto");
const db = require("./config/database");
const client = require("prom-client");
const axios = require("axios");
const { recordLoginLocation } = require("../authLogService");
const { getLocationFromIp } = require("../locationService");
require("dotenv").config();

const app = express();
const PORT = process.env.PORT || 3002;

const JWT_ACCESS_SECRET = process.env.JWT_ACCESS_SECRET || "access-secret-key";
const JWT_ACCESS_EXPIRES = process.env.JWT_ACCESS_EXPIRES || "15m";
const JWT_REFRESH_SECRET =
  process.env.JWT_REFRESH_SECRET || "refresh-secret-key";
const JWT_REFRESH_EXPIRES = process.env.JWT_REFRESH_EXPIRES || "7d";

// ==================== KONFIGURASI ====================
app.set("trust proxy", true); // Membaca IP client yang sebenarnya di belakang proxy

app.use(
  cors({
    origin: process.env.CORS_ORIGIN || "*",
    credentials: true,
  }),
);

app.use(express.json());
app.use(express.urlencoded({ extended: true }));

// ==================== UTILITIES ====================

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

const generateRefreshToken = (userId) => {
  return jwt.sign({ sub: userId }, JWT_REFRESH_SECRET, {
    expiresIn: JWT_REFRESH_EXPIRES,
    issuer: "oauth-server",
  });
};

const generateAuthorizationCode = () => {
  return crypto.randomBytes(32).toString("hex");
};

const hashPassword = async (password) => {
  return bcryptjs.hash(password, 10);
};

const verifyPassword = async (password, hash) => {
  return bcryptjs.compare(password, hash);
};

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

    // Dukungan bcrypt untuk production & plaintext untuk development dummy seed
    const isBcryptMatch = await bcryptjs
      .compare(clientSecret, client.client_secret)
      .catch(() => false);
    const isPlaintextMatch = clientSecret === client.client_secret;

    return isBcryptMatch || isPlaintextMatch ? client : null;
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

// ==================== METRICS ====================
const collectDefaultMetrics = client.collectDefaultMetrics;
collectDefaultMetrics({ register: client.register });
app.get("/api/metrics", async (req, res) => {
  res.set("Content-Type", client.register.contentType);
  res.end(await client.register.metrics());
});

// ==================== OAUTH 2.0 TOKEN ENDPOINT ====================

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
      if (!client_id || !client_secret || !username || !password) {
        return errorResponse(
          res,
          400,
          "error",
          "client_id, client_secret, username, and password are required",
        );
      }

      const client = await validateClient(client_id, client_secret);
      if (!client) {
        return errorResponse(res, 401, "error", "Invalid client credentials");
      }

      const connection = await db.getConnection();

      // Find user
      const [users] = await connection.query(
        "SELECT * FROM citizen_citizens WHERE email = ? AND is_active = TRUE",
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

      // Catat log lokasi (tidak memblokir eksekusi)
      recordLoginLocation(req, user.id, "password", null);

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
      if (!client_id || !client_secret || !refresh_token) {
        return errorResponse(
          res,
          400,
          "error",
          "client_id, client_secret, and refresh_token are required",
        );
      }

      const client = await validateClient(client_id, client_secret);
      if (!client) {
        return errorResponse(res, 401, "error", "Invalid client credentials");
      }

      const connection = await db.getConnection();

      // Find refresh token
      const [tokens] = await connection.query(
        "SELECT rt.*, u.* FROM refresh_tokens rt JOIN citizen_citizens u ON rt.user_id = u.id WHERE rt.token = ? AND rt.is_revoked = FALSE AND rt.expires_at > NOW()",
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

app.post("/oauth/introspect", async (req, res) => {
  const gatewaySecret = req.headers["x-gateway-secret"];
  if (gatewaySecret !== process.env.GATEWAY_INTERNAL_SECRET) {
    return res.status(403).json({
      active: false,
      error: "Unauthorized access to introspection endpoint",
    });
  }

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
app.post("/oauth/revoke", async (req, res) => {
  const { token, token_type_hint } = req.body;

  if (!token) {
    return errorResponse(res, 400, "error", "token parameter is required");
  }

  try {
    const connection = await db.getConnection();

    if (token_type_hint === "access_token") {
      await connection.query(
        "INSERT IGNORE INTO oauth_tokens (token_id, client_id, access_token, token_type, expires_at, is_revoked) VALUES (?, ?, ?, ?, NOW(), TRUE)",
        [crypto.randomBytes(16).toString("hex"), "unknown", token, "Bearer"],
      );
    } else if (token_type_hint === "refresh_token") {
      await connection.query(
        "UPDATE refresh_tokens SET is_revoked = TRUE WHERE token = ?",
        [token],
      );
    } else {
      // Try both if no hint
      await connection.query(
        "INSERT IGNORE INTO oauth_tokens (token_id, client_id, access_token, token_type, expires_at, is_revoked) VALUES (?, ?, ?, ?, NOW(), TRUE)",
        [crypto.randomBytes(16).toString("hex"), "unknown", token, "Bearer"],
      );
      await connection.query(
        "UPDATE refresh_tokens SET is_revoked = TRUE WHERE token = ?",
        [token],
      );
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

// app.post("/register", async (req, res) => {
//   const { name, email, password } = req.body;

//   if (!name || !email || !password) {
//     return errorResponse(
//       res,
//       422,
//       "error",
//       "name, email, and password are required",
//     );
//   }

//   if (password.length < 6) {
//     return errorResponse(
//       res,
//       422,
//       "error",
//       "Password must be at least 6 characters",
//     );
//   }

//   try {
//     const connection = await db.getConnection();

//     // Check if email exists
//     const [existing] = await connection.query(
//       "SELECT id FROM citizen_citizens WHERE email = ?",
//       [email],
//     );

//     if (existing.length > 0) {
//       connection.release();
//       return errorResponse(res, 409, "error", "Email already registered");
//     }

//     // Hash password and create user
//     const hashedPassword = await hashPassword(password);
//     const [result] = await connection.query(
//       "INSERT INTO citizen_citizens (name, email, password, oauth_provider, is_active) VALUES (?, ?, ?, ?, TRUE)",
//       [name, email, hashedPassword, "local"],
//     );

//     const userId = result.insertId;

//     // Generate tokens
//     const user = { id: userId, email, name, role: "user" };
//     const accessToken = generateAccessToken(user);
//     const refreshToken = generateRefreshToken(userId);

//     // Save refresh token
//     const expiresAt = new Date();
//     expiresAt.setDate(expiresAt.getDate() + 7);

//     await connection.query(
//       "INSERT INTO refresh_tokens (user_id, token, expires_at) VALUES (?, ?, ?)",
//       [userId, refreshToken, expiresAt],
//     );

//     connection.release();

//     return res.status(201).json({
//       status: "success",
//       code: 201,
//       data: {
//         user: {
//           id: userId,
//           name,
//           email,
//         },
//         access_token: accessToken,
//         refresh_token: refreshToken,
//         token_type: "Bearer",
//       },
//       message: "User registered successfully",
//       timestamp: new Date().toISOString(),
//       service: "auth",
//     });
//   } catch (error) {
//     console.error("Registration error:", error);
//     errorResponse(res, 500, "error", "Registration failed");
//   }
// });

app.post("/login", async (req, res) => {
  const { email, password } = req.body;

  if (!email || !password) {
    return errorResponse(res, 422, "error", "email and password are required");
  }

  try {
    const connection = await db.getConnection();

    const [users] = await connection.query(
      "SELECT * FROM citizen_citizens WHERE email = ? AND is_active = TRUE",
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

    // Catat log lokasi (tidak memblokir eksekusi)
    recordLoginLocation(req, user.id, "password", null);

    // 2. Dapatkan data lokasi untuk disertakan dalam respons
    const locationData = await getLocationFromIp(req.ip);

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
        // 3. Tambahkan data lokasi ke dalam respons
        location: locationData || { info: "Location could not be determined." },
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

// ==================== SOCIAL OAUTH ====================

// 1. In-Memory State Management (Mencegah CSRF Attack)
const oauthStates = new Map();

const generateState = (provider, returnJson) => {
  const state = crypto.randomBytes(16).toString("hex");
  oauthStates.set(state, {
    provider,
    returnJson: returnJson === "true",
    expiresAt: Date.now() + 10 * 60 * 1000, // Valid 10 menit
  });
  return state;
};

const validateState = (state, provider) => {
  const stateData = oauthStates.get(state);
  if (!stateData) return null;
  oauthStates.delete(state); // State hanya boleh dipakai 1 kali
  if (stateData.provider !== provider || stateData.expiresAt < Date.now())
    return null;
  return stateData;
};

// 2. Logic Registrasi & Binding Akun Social
const findOrCreateSocialUser = async (profile) => {
  const connection = await db.getConnection();
  try {
    await connection.beginTransaction();

    // Cek apakah akun social ini sudah dibinding dengan user lokal
    const [sa] = await connection.query(
      "SELECT user_id FROM social_accounts WHERE provider = ? AND provider_user_id = ?",
      [profile.provider, profile.id],
    );

    if (sa.length > 0) {
      const [users] = await connection.query(
        "SELECT * FROM citizen_citizens WHERE id = ?",
        [sa[0].user_id],
      );
      await connection.commit();
      return users[0];
    }

    // Jika belum dibinding, cek apakah email yang dipakai sama dengan user yg sudah ada
    let userId;
    if (profile.email) {
      const [existingUsers] = await connection.query(
        "SELECT * FROM citizen_citizens WHERE email = ?",
        [profile.email],
      );
      if (existingUsers.length > 0) {
        userId = existingUsers[0].id;
      }
    }

    // Jika email juga tidak ada, buat user lokal baru
    if (!userId) {
      const emailToUse =
        profile.email || `${profile.id}@${profile.provider}.local`;
      const [res] = await connection.query(
        "INSERT INTO citizen_citizens (name, email, avatar_url, oauth_provider, is_active) VALUES (?, ?, ?, ?, TRUE)",
        [
          profile.name || "Unknown User",
          emailToUse,
          profile.avatar_url,
          profile.provider,
        ],
      );
      userId = res.insertId;
    }

    // Insert relasi social_accounts
    await connection.query(
      "INSERT INTO social_accounts (user_id, provider, provider_user_id, provider_email, avatar_url) VALUES (?, ?, ?, ?, ?)",
      [userId, profile.provider, profile.id, profile.email, profile.avatar_url],
    );

    await connection.commit();
    const [finalUser] = await connection.query(
      "SELECT * FROM citizen_citizens WHERE id = ?",
      [userId],
    );
    return finalUser[0];
  } catch (error) {
    await connection.rollback();
    throw error;
  } finally {
    connection.release();
  }
};

// 3. Token Issuer & Response Handler
const issueInternalTokens = async (user) => {
  const accessToken = generateAccessToken(user, "social_login");
  const refreshToken = generateRefreshToken(user.id);

  const connection = await db.getConnection();
  const expiresAt = new Date();
  expiresAt.setDate(expiresAt.getDate() + 7);

  await connection.query(
    "INSERT INTO refresh_tokens (user_id, token, expires_at) VALUES (?, ?, ?)",
    [user.id, refreshToken, expiresAt],
  );
  connection.release();

  return { accessToken, refreshToken };
};

const handleSocialCallbackResponse = (res, stateData, profile, tokens) => {
  // Menampilkan log response ke terminal/console backend Auth Service
  console.log(`\n[OAUTH SUCCESS - ${profile.provider.toUpperCase()}]`);
  console.log(`- Provider ID   : ${profile.id}`);
  console.log(`- Name          : ${profile.name}`);
  console.log(`- Email         : ${profile.email}`);
  console.log(`- Access Token  : ${tokens.accessToken.substring(0, 30)}...`);
  console.log(`- Refresh Token : ${tokens.refreshToken.substring(0, 30)}...`);
  console.log(`- Mode JSON     : ${stateData.returnJson}`);
  console.log(`------------------------------------------------\n`);

  if (stateData.returnJson) {
    return res.status(200).json({
      status: "success",
      code: 200,
      data: {
        provider: profile.provider,
        user: {
          id: profile.id,
          name: profile.name,
          email: profile.email,
          oauth_provider: profile.provider,
          avatar_url: profile.avatar_url,
        },
        access_token: tokens.accessToken,
        refresh_token: tokens.refreshToken,
        token_type: "Bearer",
        expires_in: 900, // 15 mins
      },
      message: `${profile.provider} login successful`,
      timestamp: new Date().toISOString(),
      service: "auth",
    });
  }

  // Redirect to frontend (with tokens in hash fragment to hide from logs)
  const redirectUrl = `${process.env.FRONTEND_SUCCESS_URL || "http://localhost:3000/auth/success"}#access_token=${tokens.accessToken}&refresh_token=${tokens.refreshToken}`;
  res.redirect(redirectUrl);
};

// -------------------- GOOGLE --------------------
app.get("/google", (req, res) => {
  const state = generateState("google", req.query.json);
  const params = new URLSearchParams({
    client_id: process.env.GOOGLE_CLIENT_ID || "",
    redirect_uri: process.env.GOOGLE_CALLBACK_URL || "",
    response_type: "code",
    scope: "openid email profile",
    state: state,
  });
  res.redirect(
    `https://accounts.google.com/o/oauth2/v2/auth?${params.toString()}`,
  );
});

app.get("/callback/google", async (req, res) => {
  const { code, state, error } = req.query;

  if (error) return res.redirect(process.env.FRONTEND_ERROR_URL || "/error");
  const stateData = validateState(state, "google");
  if (!stateData)
    return errorResponse(res, 400, "error", "Invalid or expired state");

  try {
    const tokenRes = await axios.post("https://oauth2.googleapis.com/token", {
      client_id: process.env.GOOGLE_CLIENT_ID,
      client_secret: process.env.GOOGLE_CLIENT_SECRET,
      code,
      redirect_uri: process.env.GOOGLE_CALLBACK_URL,
      grant_type: "authorization_code",
    });

    const profileRes = await axios.get(
      "https://www.googleapis.com/oauth2/v2/userinfo",
      {
        headers: { Authorization: `Bearer ${tokenRes.data.access_token}` },
      },
    );

    const profileData = {
      provider: "google",
      id: profileRes.data.id,
      email: profileRes.data.email,
      name: profileRes.data.name,
      avatar_url: profileRes.data.picture,
    };

    const user = await findOrCreateSocialUser(profileData);
    const tokens = await issueInternalTokens(user);
    recordLoginLocation(req, user.id, "oauth", "google");
    handleSocialCallbackResponse(res, stateData, profileData, tokens);
  } catch (err) {
    console.error("Google OAuth Error:", err.response?.data || err.message);
    res.redirect(process.env.FRONTEND_ERROR_URL || "/error");
  }
});

// -------------------- GITHUB --------------------
app.get("/github", (req, res) => {
  const state = generateState("github", req.query.json);
  const params = new URLSearchParams({
    client_id: process.env.GITHUB_CLIENT_ID || "",
    redirect_uri: process.env.GITHUB_CALLBACK_URL || "",
    scope: "user:email",
    state: state,
  });
  res.redirect(`https://github.com/login/oauth/authorize?${params.toString()}`);
});

app.get("/callback/github", async (req, res) => {
  const { code, state, error } = req.query;

  if (error) return res.redirect(process.env.FRONTEND_ERROR_URL || "/error");
  const stateData = validateState(state, "github");
  if (!stateData)
    return errorResponse(res, 400, "error", "Invalid or expired state");

  try {
    const tokenRes = await axios.post(
      "https://github.com/login/oauth/access_token",
      {
        client_id: process.env.GITHUB_CLIENT_ID,
        client_secret: process.env.GITHUB_CLIENT_SECRET,
        code,
        redirect_uri: process.env.GITHUB_CALLBACK_URL,
      },
      { headers: { Accept: "application/json" } },
    );

    const ghToken = tokenRes.data.access_token;

    const profileRes = await axios.get("https://api.github.com/user", {
      headers: { Authorization: `Bearer ${ghToken}` },
    });

    let email = profileRes.data.email;
    if (!email) {
      // Request separate email endpoint jika email di-private
      const emailRes = await axios.get("https://api.github.com/user/emails", {
        headers: { Authorization: `Bearer ${ghToken}` },
      });
      const primaryEmail = emailRes.data.find((e) => e.primary && e.verified);
      email = primaryEmail ? primaryEmail.email : emailRes.data[0]?.email;
    }

    const profileData = {
      provider: "github",
      id: profileRes.data.id.toString(),
      email: email,
      name: profileRes.data.name || profileRes.data.login,
      avatar_url: profileRes.data.avatar_url,
    };

    const user = await findOrCreateSocialUser(profileData);
    const tokens = await issueInternalTokens(user);
    recordLoginLocation(req, user.id, "oauth", "github");
    handleSocialCallbackResponse(res, stateData, profileData, tokens);
  } catch (err) {
    console.error("GitHub OAuth Error:", err.response?.data || err.message);
    res.redirect(process.env.FRONTEND_ERROR_URL || "/error");
  }
});

// -------------------- FACEBOOK --------------------
app.get("/facebook", (req, res) => {
  const state = generateState("facebook", req.query.json);
  const params = new URLSearchParams({
    client_id: process.env.FACEBOOK_APP_ID || "",
    redirect_uri: process.env.FACEBOOK_CALLBACK_URL || "",
    scope: "email,public_profile",
    state: state,
  });
  res.redirect(
    `https://www.facebook.com/v19.0/dialog/oauth?${params.toString()}`,
  );
});

app.get("/callback/facebook", async (req, res) => {
  const { code, state, error } = req.query;

  if (error) return res.redirect(process.env.FRONTEND_ERROR_URL || "/error");
  const stateData = validateState(state, "facebook");
  if (!stateData)
    return errorResponse(res, 400, "error", "Invalid or expired state");

  try {
    const tokenRes = await axios.get(
      "https://graph.facebook.com/v19.0/oauth/access_token",
      {
        params: {
          client_id: process.env.FACEBOOK_APP_ID,
          client_secret: process.env.FACEBOOK_APP_SECRET,
          redirect_uri: process.env.FACEBOOK_CALLBACK_URL,
          code,
        },
      },
    );

    const fbToken = tokenRes.data.access_token;

    const profileRes = await axios.get("https://graph.facebook.com/v19.0/me", {
      params: {
        fields: "id,name,email,picture.type(large)",
        access_token: fbToken,
      },
    });

    const profileData = {
      provider: "facebook",
      id: profileRes.data.id,
      email: profileRes.data.email,
      name: profileRes.data.name,
      avatar_url: profileRes.data.picture?.data?.url,
    };

    const user = await findOrCreateSocialUser(profileData);
    const tokens = await issueInternalTokens(user);
    recordLoginLocation(req, user.id, "oauth", "facebook");
    handleSocialCallbackResponse(res, stateData, profileData, tokens);
  } catch (err) {
    console.error("Facebook OAuth Error:", err.response?.data || err.message);
    res.redirect(process.env.FRONTEND_ERROR_URL || "/error");
  }
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
