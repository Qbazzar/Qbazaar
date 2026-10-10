// Loads every scenario file in this folder (each exports an array of scenarios).
const fs = require('fs');
const path = require('path');

const SUPPORT_FILES = new Set(['index.js', 'dsl.js', 'spec.js']);

function loadScenarios() {
  return fs.readdirSync(__dirname)
    .filter((file) => file.endsWith('.js') && !SUPPORT_FILES.has(file))
    .flatMap((file) => require(path.join(__dirname, file)));
}

module.exports = { loadScenarios };
