const { defineConfig } = require("cypress");

module.exports = defineConfig({
  chromeWebSecurity: false,
  videoCompression: false,
  videoUploadOnPasses: false,
  includeShadowDom: true,
  retries: {
    runMode: 2,
    openMode: 2,
  },
  env: {
    NODE_TLS_REJECT_UNAUTHORIZED: 0,
  },
  
  e2e: {
    setupNodeEvents(on, config) {
      // implement node event listeners here
    },
  },
});
