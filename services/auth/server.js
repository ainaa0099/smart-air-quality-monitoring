const express = require("express");

const createService = (name, port) => {
  const app = express();

  app.get("/health", (req, res) => {
    res.json({ status: "healthy", service: name });
  });

  app.get("/", (req, res) => {
    res.json({ message: `Welcome to ${name}` });
  });

  app.get("/data", (req, res) => {
    res.json({
      service: name,
      data: `Mock data from ${name} at ${new Date().toISOString()}`,
      auth_user: req.headers["authorization"] ? "Authenticated" : "Anonymous",
    });
  });

  app.listen(port, () => {
    console.log(`[DUMMY] ${name} running on port ${port}`);
  });
};

// Boot all dummy services
createService("Citizen Service", 8000);
createService("Traffic Service", 8001);
createService("Environment Service", 8002);
createService("Python ML Service", 5000);
