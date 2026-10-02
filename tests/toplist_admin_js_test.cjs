const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
let count = 0;
for (const name of ['medical_toplists.php', 'medical_toplist_edit.php']) {
  const source = fs.readFileSync(path.join(__dirname, '../admin', name), 'utf8');
  for (const match of source.matchAll(/<script\b[^>]*>([\s\S]*?)<\/script>/g)) {
    const script = match[1].replace(/<\?(?:php|=)[\s\S]*?\?>/g, '"fixture"');
    if (script.trim()) { new vm.Script(script, {filename: name}); count++; }
  }
}
console.log(`Toplist admin JavaScript: ${count} inline scripts compile.`);
