// Resolves reference selectors from the behaviour inventories, so scenarios name inventory ids
// instead of copying selectors.
const { loadStreams, markUsed } = require('../src/spec');

let index = null;

function refOf(id) {
  index = index || new Map(loadStreams().flatMap((s) => s.elements).map((e) => [e.id, e.refSelector]));
  if (!index.has(id)) throw new Error(`inventory has no element ${id}`);
  markUsed(id);
  return index.get(id);
}

/** { ref, target } with the reference side taken from the inventory. */
const inventory = (id, target) => ({ ref: refOf(id), target });

module.exports = { refOf, inventory };
