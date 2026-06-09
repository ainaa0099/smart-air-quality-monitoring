const express = require("express");
const cors = require("cors");
const morgan = require("morgan");
const rateLimit = require("express-rate-limit");
const jwt = require("jsonwebtoken");
const { createProxyMiddleware } = require("http-proxy-middleware");
const axios = require("axios");
require("dotenv").config();

const app = express();
const PORT = process.env.PORT || 3001;
const OAUTH_SERVER_URL = process.env.OAUTH_SERVER_URL;

// CORS Configuration
app.use(
  cors({
    origin: process.env.CORS_ORIGIN || "*",
    credentials: true,
    methods: ["GET", "POST", "PUT", "DELETE", "OPTIONS"],
    allowedHeaders: ["Content-Type", "Authorization"],
  }),
);

app.use(express.json());
app.use(express.urlencoded({ extended: true }));

morgan.token("response-time-ms", (req, res) => {
  return res.getHeader("X-Response-Time") || 0;
});

const morganFormat =
  ':remote-addr - :remote-user [:date[clf]] ":method :url HTTP/:http-version" :status :res[content-length] ":referrer" ":user-agent" :response-time-ms ms';
app.use(morgan(morganFormat));

app.use((req, res, next) => {
  const startTime = Date.now();
  const originalJson = res.json;

  res.json = function (data) {
    const responseTime = Date.now() - startTime;
    res.setHeader("X-Response-Time", responseTime);

    if (typeof data === "object" && data) {
      data.responseTime = responseTime;
      data.timestamp = data.timestamp || new Date().toISOString();
    }

    return originalJson.call(this, data);
  };

  next();
});

const ipRateLimiter = rateLimit({
  windowMs: 15 * 60 * 1000, // 15 minutes
  max: 100, // 100 requests per windowMs
  keyGenerator: (req, res) => req.ip,
  handler: (req, res) => {
    res.status(429).json({
      status: "error",
      code: 429,
      message: "Too many requests from this IP, please try again later.",
      retryAfter: req.rateLimit.resetTime,
      timestamp: new Date().toISOString(),
      service: "gateway",
    });
  },
  skip: (req) => {
    return !!req.user;
  },
});

const tokenRateLimiter = rateLimit({
  windowMs: 60 * 60 * 1000, // 60 minutes
  max: 500, // 500 requests per windowMs for authenticated users
  keyGenerator: (req, res) => req.user?.sub || req.user?.id || req.ip,
  handler: (req, res) => {
    res.status(429).json({
      status: "error",
      code: 429,
      message: "Token rate limit exceeded, please try again later.",
      retryAfter: req.rateLimit.resetTime,
      timestamp: new Date().toISOString(),
      service: "gateway",
    });
  },
  skip: (req) => {
    return !req.user;
  },
});

const loginRateLimiter = rateLimit({
  windowMs: 15 * 60 * 1000, // 15 minutes
  max: 10, // 10 login attempts per windowMs
  keyGenerator: (req, res) => req.body.username || req.body.email || req.ip,
  handler: (req, res) => {
    res.status(429).json({
      status: "error",
      code: 429,
      message: "Too many login attempts, please try again after 15 minutes.",
      timestamp: new Date().toISOString(),
      service: "gateway",
    });
  },
});

const tokenBlacklist = new Set();

// Middleware tambahan untuk memperbaiki body yang hilang saat di proxy
const fixProxyBody = (proxyReq, req, res) => {
  if (req.body && Object.keys(req.body).length > 0) {
    const bodyData = JSON.stringify(req.body);
    proxyReq.setHeader("Content-Type", "application/json");
    proxyReq.setHeader("Content-Length", Buffer.byteLength(bodyData));
    proxyReq.write(bodyData);
  }
};

const introspectToken = async (req, res, next) => {
  const token = req.headers.authorization?.split(" ")[1];

  if (!token) {
    return res.status(401).json({
      status: "error",
      code: 401,
      message: "No authorization token provided",
      timestamp: new Date().toISOString(),
      service: "gateway",
    });
  }

  if (tokenBlacklist.has(token)) {
    return res.status(401).json({
      status: "error",
      code: 401,
      message: "Token has been revoked",
      timestamp: new Date().toISOString(),
      service: "gateway",
    });
  }

  try {
    const introspectResponse = await axios.post(
      `${OAUTH_SERVER_URL}/oauth/introspect`,
      { token },
      { timeout: 5000 },
    );

    if (!introspectResponse.data.active) {
      return res.status(401).json({
        status: "error",
        code: 401,
        message: "Token is not active or has expired",
        timestamp: new Date().toISOString(),
        service: "gateway",
      });
    }

    req.user = introspectResponse.data;
    req.token = token;
    next();
  } catch (error) {
    console.error("Token introspection error:", error.message);

    try {
      const decoded = jwt.verify(
        token,
        process.env.JWT_ACCESS_SECRET || "fallback-secret",
      );
      req.user = decoded;
      req.token = token;
      console.warn(
        "Using fallback JWT verification - OAuth server unavailable",
      );
      next();
    } catch (jwtError) {
      return res.status(401).json({
        status: "error",
        code: 401,
        message: "Invalid or expired token",
        timestamp: new Date().toISOString(),
        service: "gateway",
      });
    }
  }
};

app.use((req, res, next) => {
  const timestamp = new Date().toISOString();
  const method = req.method;
  const path = req.path;
  const ip = req.ip;

  console.log(`[${timestamp}] ${method} ${path} from ${ip}`);

  const originalJson = res.json;
  res.json = function (data) {
    const statusCode = res.statusCode;
    const responseTime = res.getHeader("X-Response-Time") || 0;
    const userId = req.user?.sub || req.user?.id || "anonymous";

    console.log(
      `[${timestamp}] Response: ${statusCode} - ${method} ${path} - User: ${userId} - ${responseTime}ms`,
    );

    return originalJson.call(this, data);
  };

  next();
});

const checkServiceHealth = async (url, serviceName) => {
  try {
    const response = await axios.get(url, { timeout: 3000 });
    return {
      service: serviceName,
      status: "healthy",
      statusCode: response.status,
    };
  } catch (error) {
    return {
      service: serviceName,
      status: "unhealthy",
      error: error.message,
    };
  }
};

app.get("/health", async (req, res) => {
  try {
    const services = [
      {
        url: `${process.env.AUTH_SERVICE_URL || "http://localhost:3002"}/health`,
        name: "auth",
      }, // Kelanjutan dari service health checks pada service lainnya
    ];

    const healthChecks = await Promise.all(
      services.map((s) => checkServiceHealth(s.url, s.name)),
    );

    const allHealthy = healthChecks.every((h) => h.status === "healthy");
    const overallStatus = allHealthy ? "healthy" : "degraded";

    res.status(allHealthy ? 200 : 503).json({
      status: overallStatus === "healthy" ? "success" : "partial",
      code: allHealthy ? 200 : 503,
      data: {
        gateway: "healthy",
        services: healthChecks,
        overallStatus: overallStatus,
        timestamp: new Date().toISOString(),
      },
      message: `Gateway health check - Status: ${overallStatus}`,
      timestamp: new Date().toISOString(),
      service: "gateway",
    });
  } catch (error) {
    console.error("Health check error:", error);
    res.status(503).json({
      status: "error",
      code: 503,
      message: "Health check failed",
      timestamp: new Date().toISOString(),
      service: "gateway",
    });
  }
});

app.post(
  "/oauth/token",
  loginRateLimiter,
  createProxyMiddleware({
    target: `${process.env.AUTH_SERVICE_URL || "http://localhost:3002"}`,
    changeOrigin: true,
    pathRewrite: {
      "^/oauth/token": "/oauth/token",
    },
    onProxyReq: fixProxyBody,
    onError: (err, req, res) => {
      res.status(503).json({
        status: "error",
        code: 503,
        message: "Auth service unavailable",
        timestamp: new Date().toISOString(),
        service: "gateway",
      });
    },
  }),
);

app.post(
  "/oauth/introspect",
  createProxyMiddleware({
    target: `${process.env.AUTH_SERVICE_URL || "http://localhost:3002"}`,
    changeOrigin: true,
    pathRewrite: {
      "^/oauth/introspect": "/oauth/introspect",
    },
    onProxyReq: fixProxyBody,
  }),
);

app.post(
  "/oauth/revoke",
  createProxyMiddleware({
    target: `${process.env.AUTH_SERVICE_URL || "http://localhost:3002"}`,
    changeOrigin: true,
    pathRewrite: {
      "^/oauth/revoke": "/oauth/revoke",
    },
    onProxyReq: fixProxyBody,
  }),
);

app.use(
  "/api/auth",
  loginRateLimiter,
  createProxyMiddleware({
    target: `${process.env.AUTH_SERVICE_URL || "http://localhost:3002"}`,
    changeOrigin: true,
    pathRewrite: {
      "^/api/auth": "",
    },
    onProxyReq: fixProxyBody,
    onError: (err, req, res) => {
      res.status(503).json({
        status: "error",
        code: 503,
        message: "Auth service unavailable",
        timestamp: new Date().toISOString(),
        service: "gateway",
      });
    },
  }),
);

app.use("/api/", ipRateLimiter, tokenRateLimiter);

// Masukkan Property service lainnya disini

// 404 handler
app.use((req, res) => {
  res.status(404).json({
    status: "error",
    code: 404,
    message: "Route not found",
    path: req.path,
    timestamp: new Date().toISOString(),
    service: "gateway",
  });
});

app.use((err, req, res, next) => {
  console.error("Gateway error:", err);

  const statusCode = err.status || err.statusCode || 500;
  const message = err.message || "Internal gateway error";

  res.status(statusCode).json({
    status: "error",
    code: statusCode,
    message: message,
    error: process.env.NODE_ENV === "development" ? err.stack : undefined,
    timestamp: new Date().toISOString(),
    service: "gateway",
  });
});

app.listen(PORT, () => {
  console.log(`
╔════════════════════════════════════════╗
║   API Gateway - Smart City Platform    ║
╚════════════════════════════════════════╝
  
  Gateway running on: http://localhost:${PORT}
  OAuth Server: ${OAUTH_SERVER_URL}
  Environment: ${process.env.NODE_ENV || "development"}
  
  Available Endpoints:
  - GET    /health                    (Gateway health & service status)
  - POST   /oauth/token               (Get access token)
  - POST   /oauth/introspect          (Validate token)
  - POST   /oauth/revoke              (Revoke token)
  - POST   /api/auth/register         (User registration)
  - POST   /api/auth/login            (User login)
  `);
});

module.exports = app;
