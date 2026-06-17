// Simple static file server for OPTICANA
// Run: node serve.mjs
// Then open: http://localhost:3000

import http from 'http'
import fs   from 'fs'
import path from 'path'
import { fileURLToPath } from 'url'

const __dirname = path.dirname(fileURLToPath(import.meta.url))
const PORT = 3000

const MIME = {
  '.html': 'text/html; charset=utf-8',
  '.css':  'text/css; charset=utf-8',
  '.js':   'application/javascript; charset=utf-8',
  '.mjs':  'application/javascript; charset=utf-8',
  '.png':  'image/png',
  '.jpg':  'image/jpeg',
  '.jpeg': 'image/jpeg',
  '.svg':  'image/svg+xml',
  '.ico':  'image/x-icon',
  '.json': 'application/json'
}

// Pages that live under /pages/ but might be requested at root level
const PAGE_REDIRECTS = new Set([
  '/landing.html', '/about.html', '/services.html',
  '/doctors.html', '/contact.html', '/forgot-password.html'
])

http.createServer((req, res) => {
  let urlPath = req.url.split('?')[0]
  if (urlPath === '/') urlPath = '/index.html'

  // Redirect bare page names to their /pages/ location
  if (PAGE_REDIRECTS.has(urlPath)) {
    res.writeHead(301, { Location: '/pages' + urlPath })
    res.end()
    return
  }

  const filePath = path.join(__dirname, urlPath)
  const ext      = path.extname(filePath).toLowerCase()
  const mimeType = MIME[ext] || 'application/octet-stream'

  fs.readFile(filePath, (err, data) => {
    if (err) {
      res.writeHead(404, { 'Content-Type': 'text/plain' })
      res.end('404 Not Found: ' + urlPath)
      return
    }
    res.writeHead(200, {
      'Content-Type': mimeType,
      'Cache-Control': 'no-cache'
    })
    res.end(data)
  })
}).listen(PORT, () => {
  console.log(`OPTICANA server running at http://localhost:${PORT}`)
})
