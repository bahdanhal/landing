/**
 * Nginx & Apache to Caddyfile Transpiler
 * 100% Client-Side Engine (Privacy-First)
 */
(function() {
  'use strict';

  // Preset Configurations
  const PRESETS = {
    nginx: {
      wordpress: `server {
    listen 80;
    server_name my-wordpress.com www.my-wordpress.com;
    root /var/www/wordpress;
    index index.php;

    client_max_body_size 64M;
    gzip on;

    location / {
        try_files $uri $uri/ /index.php?$args;
    }

    location ~ \\.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
    }
}`,
      laravel_symfony: `server {
    listen 443 ssl http2;
    server_name app.example.com;
    root /var/www/app/public;
    index index.php;

    ssl_certificate /etc/letsencrypt/live/app.example.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/app.example.com/privkey.pem;

    client_max_body_size 32M;

    add_header X-Frame-Options "DENY" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \\.php$ {
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
}`,
      nextjs: `server {
    listen 80;
    server_name nextjs.example.com;

    location / {
        proxy_pass http://127.0.0.1:3000;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}`,
      spa: `server {
    listen 80;
    server_name spa.example.com;
    root /var/www/spa/dist;
    index index.html;

    gzip on;

    location / {
        try_files $uri $uri/ /index.html;
    }
}`,
      proxy: `server {
    listen 443 ssl;
    server_name api.example.com;
    ssl_certificate /etc/ssl/certs/api.crt;
    ssl_certificate_key /etc/ssl/private/api.key;

    location / {
        proxy_pass http://10.0.0.5:8080;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    }
}`,
      static: `server {
    listen 80;
    server_name static.example.com;
    root /var/www/static;

    gzip on;
    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";
    add_header Content-Security-Policy "default-src 'self'";

    rewrite ^/legacy-page$ /new-page permanent;
    rewrite ^/docs/(.*)$ /v2/docs/$1 redirect;
}`
    },
    apache: {
      wordpress: `# WordPress .htaccess
<IfModule mod_rewrite.c>
RewriteEngine On
RewriteBase /
RewriteRule ^index\\.php$ - [L]
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule . /index.php [L]
</IfModule>

LimitRequestBody 67108864`,
      laravel_symfony: `<VirtualHost *:80>
    ServerName app.example.com
    ServerAlias www.app.example.com
    DocumentRoot /var/www/app/public

    Header set X-Frame-Options "DENY"
    Header set X-Content-Type-Options "nosniff"

    <Directory /var/www/app/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>`,
      nextjs: `<VirtualHost *:80>
    ServerName nextjs.example.com

    ProxyPreserveHost On
    ProxyPass / http://127.0.0.1:3000/
    ProxyPassReverse / http://127.0.0.1:3000/
</VirtualHost>`,
      spa: `<IfModule mod_rewrite.c>
RewriteEngine On
RewriteBase /
RewriteRule ^index\\.html$ - [L]
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule . /index.html [L]
</IfModule>`,
      proxy: `<VirtualHost *:443>
    ServerName api.example.com
    SSLEngine on
    SSLCertificateFile /etc/ssl/certs/api.crt
    SSLCertificateKeyFile /etc/ssl/private/api.key

    ProxyPass / http://10.0.0.5:8080/
    ProxyPassReverse / http://10.0.0.5:8080/
</VirtualHost>`,
      static: `ServerName static.example.com
DocumentRoot /var/www/static

Header set X-Frame-Options "SAMEORIGIN"
Header set X-Content-Type-Options "nosniff"

RewriteEngine On
RewriteRule ^legacy-page$ /new-page [R=301,L]
RewriteRule ^docs/(.*)$ /v2/docs/$1 [R=302,L]`
    }
  };

  // State
  let currentServer = 'nginx';

  // DOM Elements
  const inputEl = document.getElementById('config-input');
  const outputCodeEl = document.getElementById('caddy-output-code');
  const inputStatsEl = document.getElementById('input-stats');
  const outputStatsEl = document.getElementById('output-stats');
  const sourceTitleEl = document.getElementById('source-panel-title');
  const advisoriesContainer = document.getElementById('advisories-container');
  const omittedListEl = document.getElementById('omitted-list');
  const featuresListEl = document.getElementById('features-list');
  const copyBtn = document.getElementById('btn-copy-caddyfile');
  const downloadBtn = document.getElementById('btn-download-caddyfile');
  const clearBtn = document.getElementById('btn-clear-input');

  // Parser Functions
  function transpileNginx(raw) {
    const lines = raw.split(/\r\n|\r|\n/);
    const advisories = [];
    const detectedFeatures = [];
    const omitted = [];

    const siteAddresses = [];
    let rootPath = null;
    let phpFastcgi = null;
    let fileServerNeeded = false;
    let encodeDirective = null;
    const headers = [];
    const reverseProxies = [];
    const redirections = [];
    let tryFiles = null;
    let requestBodyLimit = null;
    let basicAuth = false;

    let hasSslDirective = false;
    let hasWebSocketHeaders = false;
    let hasCompression = false;

    for (let rawLine of lines) {
      const line = rawLine.trim();
      if (!line || line.startsWith('#')) continue;

      const clean = line.replace(/;$/, '');

      // Server name
      const serverMatch = clean.match(/^server_name\s+(.+)$/i);
      if (serverMatch) {
        const names = serverMatch[1].trim().split(/\s+/);
        for (let name of names) {
          name = name.trim();
          if (name !== '_' && name !== 'localhost' && !name.startsWith('$')) {
            siteAddresses.push(name);
          } else if (name === 'localhost') {
            siteAddresses.push('localhost');
          }
        }
        detectedFeatures.push('Server names (' + siteAddresses.join(', ') + ')');
        continue;
      }

      // Root
      const rootMatch = clean.match(/^root\s+(.+)$/i);
      if (rootMatch) {
        rootPath = rootMatch[1].trim();
        fileServerNeeded = true;
        detectedFeatures.push('Document root (' + rootPath + ')');
        continue;
      }

      // SSL Directives
      if (/^ssl_(certificate|certificate_key|protocols|ciphers|prefer_server_ciphers|session_)/i.test(clean)) {
        hasSslDirective = true;
        omitted.push(line);
        continue;
      }

      // Proxy pass
      const proxyMatch = clean.match(/^proxy_pass\s+(https?:\/\/[^\s;]+)/i);
      if (proxyMatch) {
        let upstream = proxyMatch[1].trim().replace(/^https?:\/\//, '');
        reverseProxies.push({ path: null, upstream });
        detectedFeatures.push('Reverse Proxy (' + upstream + ')');
        continue;
      }

      // Proxy set header
      const headerMatch = clean.match(/^proxy_set_header\s+([^\s]+)\s+(.+)$/i);
      if (headerMatch) {
        const hName = headerMatch[1].toLowerCase().trim();
        const hVal = headerMatch[2].trim();
        if (hName === 'upgrade' || hName === 'connection' || hVal.includes('upgrade')) {
          hasWebSocketHeaders = true;
          omitted.push(line);
        } else if (['host', 'x-real-ip', 'x-forwarded-for', 'x-forwarded-proto'].includes(hName)) {
          omitted.push(line);
        } else {
          headers.push('header_up ' + headerMatch[1] + ' ' + hVal);
        }
        continue;
      }

      // FastCGI pass
      const fpmMatch = clean.match(/^fastcgi_pass\s+(.+)$/i);
      if (fpmMatch) {
        let target = fpmMatch[1].trim();
        if (target.startsWith('unix:')) {
          phpFastcgi = 'unix//' + target.slice(5).replace(/^\/+/, '');
        } else {
          phpFastcgi = target;
        }
        fileServerNeeded = true;
        detectedFeatures.push('PHP-FPM Router (' + phpFastcgi + ')');
        continue;
      }

      // Try files
      const tryMatch = clean.match(/^try_files\s+(.+)$/i);
      if (tryMatch) {
        tryFiles = tryMatch[1].trim().split(/\s+/);
        fileServerNeeded = true;
        continue;
      }

      // Add Header
      const addHeaderMatch = clean.match(/^add_header\s+([^\s]+)\s+([^;]+?)(?:\s+always)?$/i);
      if (addHeaderMatch) {
        headers.push('header ' + addHeaderMatch[1].trim() + ' ' + addHeaderMatch[2].trim());
        detectedFeatures.push('Security/Response Header (' + addHeaderMatch[1] + ')');
        continue;
      }

      // Max body size
      const maxBodyMatch = clean.match(/^client_max_body_size\s+(\d+[kKmMgG]?)/i);
      if (maxBodyMatch) {
        let size = maxBodyMatch[1].toUpperCase().trim();
        if (size.endsWith('M') || size.endsWith('K') || size.endsWith('G')) {
          size += 'B';
        }
        requestBodyLimit = size;
        detectedFeatures.push('Max Body Size (' + size + ')');
        continue;
      }

      // Gzip
      if (/^gzip\s+on/i.test(clean)) {
        hasCompression = true;
        encodeDirective = 'encode zstd gzip';
        detectedFeatures.push('Zstandard + Gzip Compression');
        continue;
      }

      // Rewrite / Redirect
      const rewriteMatch = clean.match(/^rewrite\s+\^?([^\s]+)\s+([^\s]+)(?:\s+(permanent|redirect))?$/i);
      if (rewriteMatch) {
        let from = rewriteMatch[1];
        let to = rewriteMatch[2];
        let type = rewriteMatch[3] && rewriteMatch[3].toLowerCase() === 'permanent' ? 'permanent' : 'temporary';

        let caddyFrom = from.replace(/\(\.\*\)/g, '*').replace(/\$$/, '');
        if (!caddyFrom.startsWith('/')) caddyFrom = '/' + caddyFrom;
        let caddyTo = to.replace(/\$1/g, '{1}').replace(/\$2/g, '{2}');
        redirections.push('redir ' + caddyFrom + ' ' + caddyTo + ' ' + type);
        detectedFeatures.push('Redirect (' + caddyFrom + ' → ' + caddyTo + ')');
        continue;
      }

      // Return redirect
      const returnMatch = clean.match(/^return\s+(301|302|307|308)\s+(.+)$/i);
      if (returnMatch) {
        let code = returnMatch[1];
        let target = returnMatch[2].trim().replace(/\$host/g, '{host}').replace(/\$request_uri/g, '{uri}').replace(/\$uri/g, '{uri}');
        let type = ['301', '308'].includes(code) ? 'permanent' : 'temporary';
        redirections.push('redir ' + target + ' ' + type);
        detectedFeatures.push('Status ' + code + ' Redirection');
        continue;
      }

      // Basic Auth
      if (/^auth_basic\s+/i.test(clean)) {
        basicAuth = true;
        detectedFeatures.push('HTTP Basic Authentication');
        continue;
      }
    }

    // Advisories
    if (hasSslDirective) {
      advisories.push({
        severity: 'info',
        title: 'Automatic HTTPS Enabled',
        desc: "Caddy automatically manages SSL certificates via Let's Encrypt / ZeroSSL. Explicit certificate paths were removed as redundant.",
        suggestion: 'No manual certificate renewal cron jobs or certbot scripts required.'
      });
    }

    if (hasWebSocketHeaders) {
      advisories.push({
        severity: 'info',
        title: 'Native WebSocket Support',
        desc: 'Caddy reverse_proxy handles WebSocket upgrade headers and connection states automatically out of the box.',
        suggestion: 'You do not need to configure Connection/Upgrade proxy headers manually.'
      });
    }

    if (requestBodyLimit) {
      advisories.push({
        severity: 'warning',
        title: 'Custom Request Body Limit',
        desc: 'Nginx client_max_body_size was translated to Caddy request_body max_size.',
        suggestion: `Configured: request_body { max_size ${requestBodyLimit} }`
      });
    }

    if (phpFastcgi) {
      advisories.push({
        severity: 'tip',
        title: 'PHP-FPM Router Consolidated',
        desc: 'Nginx try_files + fastcgi_pass and param includes are consolidated into a single php_fastcgi directive in Caddy.',
        suggestion: 'php_fastcgi handles routing to index.php and path sanitization automatically.'
      });
    }

    if (hasCompression) {
      advisories.push({
        severity: 'tip',
        title: 'Modern Compression Enabled',
        desc: 'Configured "encode zstd gzip" for optimal payload compression with Zstandard and Gzip fallback.',
        suggestion: 'Caddy dynamically compresses compatible MIME responses with zero extra configuration.'
      });
    }

    // Assemble Caddyfile Output
    const siteHeader = siteAddresses.length === 0 ? 'example.com' : Array.from(new Set(siteAddresses)).join(', ');
    const linesOut = [];
    linesOut.push(siteHeader + ' {');

    if (encodeDirective) linesOut.push('    ' + encodeDirective);
    if (rootPath) linesOut.push('    root * ' + rootPath);

    if (requestBodyLimit) {
      linesOut.push('    request_body {');
      linesOut.push('        max_size ' + requestBodyLimit);
      linesOut.push('    }');
    }

    for (let h of headers) linesOut.push('    ' + h);
    for (let r of redirections) linesOut.push('    ' + r);

    if (basicAuth) {
      linesOut.push('    basic_auth {');
      linesOut.push('        # Generate hash with: caddy hash-password');
      linesOut.push('        username $2a$14$...');
      linesOut.push('    }');
    }

    if (phpFastcgi) {
      linesOut.push('    php_fastcgi ' + phpFastcgi);
    } else if (tryFiles) {
      const last = tryFiles[tryFiles.length - 1];
      if (last === '/index.html' || last.endsWith('index.html')) {
        linesOut.push('    try_files {path} /index.html');
      }
    }

    for (let p of reverseProxies) {
      const prefix = p.path ? p.path + ' ' : '';
      linesOut.push('    reverse_proxy ' + prefix + p.upstream);
    }

    if (fileServerNeeded) {
      linesOut.push('    file_server');
    }

    if (linesOut.length === 1) {
      linesOut.push('    # Reverse proxy or file server configuration');
      linesOut.push('    reverse_proxy 127.0.0.1:3000');
    }

    linesOut.push('}');

    return {
      caddyfile: linesOut.join('\n'),
      advisories,
      detectedFeatures: Array.from(new Set(detectedFeatures)),
      omitted: Array.from(new Set(omitted))
    };
  }

  function transpileApache(raw) {
    const lines = raw.split(/\r\n|\r|\n/);
    const advisories = [];
    const detectedFeatures = [];
    const omitted = [];

    const siteAddresses = [];
    let rootPath = null;
    let phpFastcgi = null;
    let fileServerNeeded = false;
    let encodeDirective = null;
    const headers = [];
    const reverseProxies = [];
    const redirections = [];
    let tryFiles = null;
    let requestBodyLimit = null;
    let basicAuth = false;

    let hasSslDirective = false;
    let isWpRewrite = false;
    let isSpaRewrite = false;

    for (let rawLine of lines) {
      const line = rawLine.trim();
      if (!line || line.startsWith('#')) continue;

      // ServerName / ServerAlias
      const sNameMatch = line.match(/^ServerName\s+(.+)$/i);
      if (sNameMatch) {
        const name = sNameMatch[1].trim();
        if (name !== '_' && name !== 'localhost') siteAddresses.push(name);
        detectedFeatures.push('ServerName (' + name + ')');
        continue;
      }

      const sAliasMatch = line.match(/^ServerAlias\s+(.+)$/i);
      if (sAliasMatch) {
        const aliases = sAliasMatch[1].trim().split(/\s+/);
        for (let a of aliases) {
          if (a) siteAddresses.push(a);
        }
        detectedFeatures.push('ServerAlias (' + aliases.join(', ') + ')');
        continue;
      }

      // DocumentRoot
      const docMatch = line.match(/^DocumentRoot\s+["']?([^"']+)["']?$/i);
      if (docMatch) {
        rootPath = docMatch[1].trim();
        fileServerNeeded = true;
        detectedFeatures.push('DocumentRoot (' + rootPath + ')');
        continue;
      }

      // SSL
      if (/^(SSLEngine|SSLCertificateFile|SSLCertificateKeyFile|SSLCACertificateFile|SSLProtocol|SSLCipherSuite)/i.test(line)) {
        hasSslDirective = true;
        omitted.push(line);
        continue;
      }

      // ProxyPass
      const proxyMatch = line.match(/^ProxyPass\s+([^\s]+)\s+([^\s]+)$/i);
      if (proxyMatch) {
        const pPath = proxyMatch[1].trim();
        let upstream = proxyMatch[2].trim().replace(/^https?:\/\//, '').replace(/\/+$/, '');
        reverseProxies.push({ path: pPath === '/' ? null : pPath, upstream });
        detectedFeatures.push('ProxyPass (' + upstream + ')');
        continue;
      }

      if (/^ProxyPassReverse\s+/i.test(line)) {
        omitted.push(line);
        continue;
      }

      // Headers
      const headerMatch = line.match(/^Header\s+(?:always\s+)?set\s+([^\s]+)\s+["']?([^"']+)["']?$/i);
      if (headerMatch) {
        headers.push('header ' + headerMatch[1].trim() + ' "' + headerMatch[2].trim() + '"');
        detectedFeatures.push('Header (' + headerMatch[1] + ')');
        continue;
      }

      // LimitRequestBody
      const limitMatch = line.match(/^LimitRequestBody\s+(\d+)$/i);
      if (limitMatch) {
        const bytes = parseInt(limitMatch[1], 10);
        const mb = Math.round((bytes / (1024 * 1024)) * 10) / 10;
        requestBodyLimit = (mb === Math.floor(mb) ? Math.floor(mb) : mb) + 'MB';
        detectedFeatures.push('LimitRequestBody (' + requestBodyLimit + ')');
        continue;
      }

      // Deflate
      if (/DEFLATE|mod_deflate/i.test(line)) {
        encodeDirective = 'encode zstd gzip';
        detectedFeatures.push('Zstandard + Gzip Compression');
        continue;
      }

      // RewriteRule
      const rewriteMatch = line.match(/^RewriteRule\s+([^\s]+)\s+([^\s]+)(?:\s+\[(.*)\])?$/i);
      if (rewriteMatch) {
        const pattern = rewriteMatch[1].trim();
        const target = rewriteMatch[2].trim();
        const flags = rewriteMatch[3] ? rewriteMatch[3].toUpperCase().trim() : '';

        if ((pattern === '.' || pattern === '^.*$') && target.includes('index.php')) {
          isWpRewrite = true;
          phpFastcgi = 'unix//run/php/php-fpm.sock';
          fileServerNeeded = true;
          detectedFeatures.push('PHP Front Controller / WordPress Rewrite');
          continue;
        }

        if ((pattern === '.' || pattern === '^.*$') && target.includes('index.html')) {
          isSpaRewrite = true;
          tryFiles = ['{path}', '/index.html'];
          fileServerNeeded = true;
          detectedFeatures.push('SPA HTML5 History Fallback');
          continue;
        }

        let type = null;
        if (flags.includes('R=301') || flags.includes('R=308')) type = 'permanent';
        else if (flags.includes('R=302') || flags.includes('R')) type = 'temporary';

        if (type) {
          let caddyFrom = pattern.replace(/^\^/, '').replace(/\$$/, '').replace(/\(\.\*\)/g, '*');
          if (!caddyFrom.startsWith('/')) caddyFrom = '/' + caddyFrom;
          let caddyTo = target.replace(/\$1/g, '{1}').replace(/\$2/g, '{2}');
          redirections.push('redir ' + caddyFrom + ' ' + caddyTo + ' ' + type);
          detectedFeatures.push('RewriteRule Redirect (' + caddyFrom + ' → ' + caddyTo + ')');
          continue;
        }
      }

      // Basic Auth
      if (/^AuthType\s+Basic/i.test(line) || /^Require\s+valid-user/i.test(line)) {
        basicAuth = true;
        detectedFeatures.push('HTTP Basic Authentication');
        continue;
      }
    }

    if (hasSslDirective) {
      advisories.push({
        severity: 'info',
        title: 'Automatic HTTPS Enabled',
        desc: "Apache SSLCertificateFile and SSLEngine were removed. Caddy automatically manages certificates via Let's Encrypt / ZeroSSL.",
        suggestion: 'Zero SSL configuration needed in Caddyfile.'
      });
    }

    if (requestBodyLimit) {
      advisories.push({
        severity: 'warning',
        title: 'LimitRequestBody Translated',
        desc: 'Apache LimitRequestBody was translated into Caddy request_body max_size.',
        suggestion: `Configured: request_body { max_size ${requestBodyLimit} }`
      });
    }

    if (isWpRewrite) {
      advisories.push({
        severity: 'tip',
        title: 'Front-Controller / WordPress Simplified',
        desc: 'Apache mod_rewrite front-controller rules were transformed into idiomatic php_fastcgi and file_server.',
        suggestion: 'Adjust php_fastcgi socket path if your PHP-FPM pool is on a different address.'
      });
    }

    // Output formatting
    const siteHeader = siteAddresses.length === 0 ? 'example.com' : Array.from(new Set(siteAddresses)).join(', ');
    const linesOut = [];
    linesOut.push(siteHeader + ' {');

    if (encodeDirective) linesOut.push('    ' + encodeDirective);
    if (rootPath) linesOut.push('    root * ' + rootPath);

    if (requestBodyLimit) {
      linesOut.push('    request_body {');
      linesOut.push('        max_size ' + requestBodyLimit);
      linesOut.push('    }');
    }

    for (let h of headers) linesOut.push('    ' + h);
    for (let r of redirections) linesOut.push('    ' + r);

    if (basicAuth) {
      linesOut.push('    basic_auth {');
      linesOut.push('        # Generate hash with: caddy hash-password');
      linesOut.push('        username $2a$14$...');
      linesOut.push('    }');
    }

    if (phpFastcgi) {
      linesOut.push('    php_fastcgi ' + phpFastcgi);
    } else if (isSpaRewrite && tryFiles) {
      linesOut.push('    try_files {path} /index.html');
    }

    for (let p of reverseProxies) {
      const prefix = p.path ? p.path + ' ' : '';
      linesOut.push('    reverse_proxy ' + prefix + p.upstream);
    }

    if (fileServerNeeded || (rootPath && reverseProxies.length === 0)) {
      linesOut.push('    file_server');
    }

    if (linesOut.length === 1) {
      linesOut.push('    # Reverse proxy or file server configuration');
      linesOut.push('    file_server');
    }

    linesOut.push('}');

    return {
      caddyfile: linesOut.join('\n'),
      advisories,
      detectedFeatures: Array.from(new Set(detectedFeatures)),
      omitted: Array.from(new Set(omitted))
    };
  }

  // Render Functions
  function runTranspiler() {
    const raw = inputEl.value;
    const linesCount = raw.trim() ? raw.split('\n').length : 0;
    inputStatsEl.textContent = `${linesCount} lines · ${raw.length} chars`;

    if (!raw.trim()) {
      outputCodeEl.textContent = '# Caddyfile output will appear here in real-time...';
      outputStatsEl.textContent = '0 lines';
      renderAdvisories([]);
      renderList(omittedListEl, []);
      renderList(featuresListEl, []);
      return;
    }

    const result = currentServer === 'apache' ? transpileApache(raw) : transpileNginx(raw);

    outputCodeEl.textContent = result.caddyfile;
    const outLines = result.caddyfile.split('\n').length;
    outputStatsEl.textContent = `${outLines} lines · ${result.caddyfile.length} chars`;

    renderAdvisories(result.advisories);
    renderList(omittedListEl, result.omitted);
    renderList(featuresListEl, result.detectedFeatures);
  }

  function renderAdvisories(advisories) {
    if (!advisories || advisories.length === 0) {
      advisoriesContainer.innerHTML = `
        <div class="advisory-card advisory-empty">
          <p>No special migration advisories for this configuration.</p>
        </div>
      `;
      return;
    }

    advisoriesContainer.innerHTML = advisories.map(a => {
      const icon = a.severity === 'warning' ? '⚠️' : (a.severity === 'tip' ? '💡' : 'ℹ️');
      return `
        <div class="advisory-card ${a.severity}">
          <div class="advisory-title">${icon} ${escapeHtml(a.title)}</div>
          <div class="advisory-desc">${escapeHtml(a.desc)}</div>
          ${a.suggestion ? `<div class="advisory-suggestion">👉 ${escapeHtml(a.suggestion)}</div>` : ''}
        </div>
      `;
    }).join('');
  }

  function renderList(containerEl, items) {
    if (!items || items.length === 0) {
      containerEl.innerHTML = '<li class="empty-hint">None detected</li>';
      return;
    }
    containerEl.innerHTML = items.map(item => `<li>${escapeHtml(item)}</li>`).join('');
  }

  function escapeHtml(str) {
    return str.replace(/&/g, '&amp;')
              .replace(/</g, '&lt;')
              .replace(/>/g, '&gt;')
              .replace(/"/g, '&quot;');
  }

  // Event Listeners & UI Binding
  function init() {
    inputEl.addEventListener('input', runTranspiler);

    // Server Switcher Buttons
    document.querySelectorAll('.btn-toggle').forEach(btn => {
      btn.addEventListener('click', () => {
        document.querySelectorAll('.btn-toggle').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        currentServer = btn.getAttribute('data-server');

        if (currentServer === 'apache') {
          sourceTitleEl.textContent = 'Apache (.htaccess / VirtualHost)';
          inputEl.placeholder = 'Paste your Apache .htaccess or <VirtualHost> configuration here...';
        } else {
          sourceTitleEl.textContent = 'Nginx (nginx.conf / server)';
          inputEl.placeholder = 'Paste your Nginx server { ... } configuration or location blocks here...';
        }

        // Load default preset for switched server
        loadPreset('wordpress');
      });
    });

    // Preset Buttons
    document.querySelectorAll('.btn-preset').forEach(btn => {
      btn.addEventListener('click', () => {
        const presetId = btn.getAttribute('data-preset');
        loadPreset(presetId);
      });
    });

    // Clear Button
    clearBtn.addEventListener('click', () => {
      inputEl.value = '';
      runTranspiler();
      inputEl.focus();
    });

    // Copy Button
    copyBtn.addEventListener('click', () => {
      const caddyfile = outputCodeEl.textContent;
      if (!caddyfile || caddyfile.startsWith('#')) return;

      navigator.clipboard.writeText(caddyfile).then(() => {
        const originalText = copyBtn.textContent;
        copyBtn.textContent = '✅ Copied!';
        setTimeout(() => {
          copyBtn.textContent = originalText;
        }, 2000);
      });
    });

    // Download Button
    downloadBtn.addEventListener('click', () => {
      const caddyfile = outputCodeEl.textContent;
      if (!caddyfile || caddyfile.startsWith('#')) return;

      const blob = new Blob([caddyfile], { type: 'text/plain;charset=utf-8' });
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = 'Caddyfile';
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
      URL.revokeObjectURL(url);
    });

    // Initial load: WordPress preset
    loadPreset('wordpress');
  }

  function loadPreset(presetId) {
    const serverPresets = PRESETS[currentServer] || PRESETS.nginx;
    if (serverPresets[presetId]) {
      inputEl.value = serverPresets[presetId];
      runTranspiler();
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
