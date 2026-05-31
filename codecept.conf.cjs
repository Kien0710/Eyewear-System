/** @type {CodeceptJS.MainConfig} */
exports.config = {
  tests: './tests/*_test.js',
  output: './output',
  helpers: {
    Playwright: {
      browser: 'chromium',
      url: 'http://localhost/Eyewear-System/frontend',
      show: true
    }
  },
  plugins: {},
  name: 'Eyewear-System'
}