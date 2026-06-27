#Requires -Version 5.1

param(
    [string]$InstallDir = "$env:ProgramFiles\Corex",
    [string]$DataDir = "$env:LOCALAPPDATA\Corex",
    [string]$LogDir = "$DataDir\logs",
    [int]$Port = 80,
    [int]$SslPort = 443
)

$ErrorActionPreference = 'Stop'
$LogDir = New-Item -ItemType Directory -Path $LogDir -Force | Select-Object -ExpandProperty FullName
$LogFile = "$LogDir\setup-nginx-windows.log"
$ConfDir = New-Item -ItemType Directory -Path "$InstallDir\conf" -Force | Select-Object -ExpandProperty FullName
$WwwDir = New-Item -ItemType Directory -Path "$InstallDir\www" -Force | Select-Object -ExpandProperty FullName
$SslDir = New-Item -ItemType Directory -Path "$InstallDir\ssl" -Force | Select-Object -ExpandProperty FullName
$NginxDir = "$InstallDir\tools\nginx"
$NginxLogDir = New-Item -ItemType Directory -Path "$DataDir\logs\nginx" -Force | Select-Object -ExpandProperty FullName

function Log { param([string]$M) Write-Host "$M" -ForegroundColor Gray; "$M" | Out-File $LogFile -Encoding utf8 -Append }
function Ok { param([string]$M) Write-Host "✓ $M" -ForegroundColor Green }

Log "=== Nginx Configuration for Windows ==="

$nginxConf = @"
worker_processes  2;
error_log  $NginxLogDir\error.log warn;
pid        $NginxDir\logs\nginx.pid;
events {
    worker_connections  1024;
    use iocp;
    multi_accept on;
}
http {
    include       mime.types;
    default_type  application/octet-stream;
    log_format  main  '`$remote_addr - `$remote_user [`$time_local] "`$request" '
                      '`$status `$body_bytes_sent "`$http_referer" '
                      '"`$http_user_agent" "`$http_x_forwarded_for"';
    access_log  $NginxLogDir\access.log main buffer=32k flush=5s;
    sendfile        on;
    tcp_nopush      on;
    tcp_nodelay     on;
    keepalive_timeout  65;
    types_hash_max_size 2048;
    client_max_body_size 100M;

    # Gzip
    gzip on;
    gzip_vary on;
    gzip_proxied any;
    gzip_comp_level 6;
    gzip_types text/plain text/css text/xml application/json application/javascript application/xml+rss application/atom+xml image/svg+xml;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;

    # Laravel backend via PHP-FPM
    server {
        listen       $Port;
        server_name corex.local localhost;
        root        $WwwDir;
        index       index.php index.html;

        access_log  $NginxLogDir\corex-access.log main buffer=32k flush=5s;
        error_log   $NginxLogDir\corex-error.log;

        location / {
            try_files \$uri \$uri/ /index.php?\$query_string;
        }

        location ~ \\.php\$ {
            fastcgi_pass   127.0.0.1:9000;
            fastcgi_index  index.php;
            fastcgi_param  SCRIPT_FILENAME  \$document_root\$fastcgi_script_name;
            fastcgi_param  PATH_INFO        \$fastcgi_path_info;
            include        fastcgi_params;

            fastcgi_buffers 16 16k;
            fastcgi_buffer_size 32k;
            fastcgi_connect_timeout 300;
            fastcgi_send_timeout 300;
            fastcgi_read_timeout 300;
        }

        location ~ /\\.ht {
            deny all;
        }

        # Static assets cache
        location ~* \\.(jpg|jpeg|png|gif|ico|css|js|svg|woff|woff2|ttf|eot)\$ {
            expires 30d;
            add_header Cache-Control "public, immutable";
        }
    }

    # AI Gateway reverse proxy
    server {
        listen       $SslPort default_server ssl http2;
        server_name  api.corex.local localhost;

        ssl_certificate     $SslDir\corex-local.pem;
        ssl_certificate_key $SslDir\corex-local.key;
        ssl_protocols TLSv1.2 TLSv1.3;
        ssl_ciphers HIGH:!aNULL:!MD5;

        access_log  $NginxLogDir\api-access.log main buffer=32k flush=5s;
        error_log   $NginxLogDir\api-error.log;

        location / {
            proxy_pass http://127.0.0.1:8001;
            proxy_http_version 1.1;
            proxy_set_header Upgrade \$http_upgrade;
            proxy_set_header Connection 'upgrade';
            proxy_set_header Host \$host;
            proxy_set_header X-Real-IP \$remote_addr;
            proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
            proxy_set_header X-Forwarded-Proto \$scheme;
            proxy_cache_bypass \$http_upgrade;

            proxy_connect_timeout 120;
            proxy_send_timeout 120;
            proxy_read_timeout 120;
            proxy_buffering off;
        }
    }
}
"@

$nginxConfPath = "$ConfDir\nginx.conf"
$nginxConf | Out-File $nginxConfPath -Encoding ascii -NoNewline
Ok "Nginx configuration written: $nginxConfPath"

# Create mime.types if it doesn't exist
$mimePath = "$ConfDir\mime.types"
if (-not (Test-Path $mimePath)) {
    @"
types {
    text/html                             html htm shtml;
    text/css                              css;
    text/xml                              xml;
    image/gif                             gif;
    image/jpeg                            jpeg jpg;
    application/javascript                js;
    application/atom+xml                  atom;
    application/rss+xml                   rss;
    application/json                      json;
    application/pdf                       pdf;
    application/xml                       xml;
    application/zip                       zip;
    application/gzip                      gzip;
    application/octet-stream              exe dll;
    image/png                             png;
    image/svg+xml                         svg svgz;
    image/x-icon                          ico;
    font/woff                             woff;
    font/woff2                            woff2;
    font/ttf                              ttf;
    font/eot                              eot;
}
"@ | Out-File $mimePath -Encoding ascii
    Ok "MIME types: $mimePath"
}

# Copy mime.types reference to nginx directory if it exists
if (Test-Path "$NginxDir\conf\mime.types") {
    Copy-Item "$NginxDir\conf\mime.types" $ConfDir -Force
    Log "Copied mime.types from nginx distro"
}

# Create staging index
$indexPath = "$WwwDir\index.html"
@"
<!DOCTYPE html>
<html><head><title>Corex</title><meta charset="utf-8">
<style>body{font-family:sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;background:#0f172a;color:#e2e8f0}h1{font-size:2.5rem}a{color:#60a5fa}</style>
</head><body><div style="text-align:center">
<h1>⚡ Corex</h1><p>Windows Desktop — Running</p>
<p><a href="/phpinfo.php">PHP Info</a> | <a href="/api/health">API Health</a></p>
</div></body></html>
"@ | Out-File $indexPath -Encoding ascii
Log "Index page: $indexPath"

# Create nginx logs directory
New-Item -ItemType Directory -Path "$NginxDir\logs" -Force -ErrorAction SilentlyContinue | Out-Null

Log "Log: $LogFile"
