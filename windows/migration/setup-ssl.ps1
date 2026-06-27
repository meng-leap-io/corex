#Requires -Version 5.1
#Requires -RunAsAdministrator

param(
    [string]$InstallDir = "$env:ProgramFiles\Corex",
    [string]$DataDir = "$env:LOCALAPPDATA\Corex",
    [string]$LogDir = "$DataDir\logs"
)

$ErrorActionPreference = 'Stop'
$LogDir = New-Item -ItemType Directory -Path $LogDir -Force | Select-Object -ExpandProperty FullName
$LogFile = "$LogDir\setup-ssl.log"

$sslDir = New-Item -ItemType Directory -Path "$InstallDir\ssl" -Force | Select-Object -ExpandProperty FullName

function Log { param([string]$M) Write-Host "$M" -ForegroundColor Gray; "$M" | Out-File $LogFile -Encoding utf8 -Append }
function Ok  { param([string]$M) Write-Host "✓ $M" -ForegroundColor Green }
function Warn { param([string]$M) Write-Host "⚠ $M" -ForegroundColor Yellow }
function Err { param([string]$M) Write-Host "✗ $M" -ForegroundColor Red }

Log "=== Local SSL Certificate Setup ==="

# Check if OpenSSL is available
$hasOpenssl = (Get-Command openssl -ErrorAction SilentlyContinue) -ne $null

if (-not $hasOpenssl) {
    Log "OpenSSL not in PATH. Checking common locations..."
    $opensslPaths = @(
        "$env:ProgramFiles\OpenSSL\bin\openssl.exe",
        "${env:ProgramFiles(x86)}\OpenSSL\bin\openssl.exe",
        "$env:LOCALAPPDATA\Programs\OpenSSL\bin\openssl.exe",
        "$InstallDir\tools\openssl\openssl.exe"
    )
    $opensslPath = $opensslPaths | Where-Object { Test-Path $_ } | Select-Object -First 1
    if ($opensslPath) {
        $hasOpenssl = $true
        $env:Path = "$(Split-Path $opensslPath);$env:Path"
        Log "Found OpenSSL at: $opensslPath"
    }
}

if (-not $hasOpenssl) {
    # Use PowerShell to generate self-signed cert (Windows-native)
    Log "OpenSSL not found. Using PowerShell's New-SelfSignedCertificate..."
    Create-CertificateViaPowerShell
} else {
    Create-CertificateViaOpenSSL
}

# Trust the certificate
Install-CertificateToTrustStore

Ok "SSL setup complete"

# ═══════════════════════════════════════════════════════════════════════════

function Create-CertificateViaPowerShell {
    $certName = "Corex Local Development"
    $certPath = "$sslDir\corex-local.pfx"
    $certCerPath = "$sslDir\corex-local.cer"
    $certKeyPath = "$sslDir\corex-local.key"
    $certPemPath = "$sslDir\corex-local.pem"
    $password = (Get-Date -Format 'yyyyMMddHHmmss') -replace '[^0-9]', ''

    # Check if cert already exists with same CN
    $existing = Get-ChildItem Cert:\LocalMachine\My | Where-Object { $_.Subject -match "CN=corex.local" }
    if ($existing) {
        Log "Certificate 'corex.local' already exists in LocalMachine\My"
        # Export it
        $pwd = ConvertTo-SecureString $password -AsPlainText -Force
        Export-PfxCertificate -Cert $existing[0] -FilePath $certPath -Password $pwd -Force | Out-Null
        Export-Certificate -Cert $existing[0] -FilePath $certCerPath -Type CERT -Force | Out-Null
        Ok "Exported existing certificate"
        return
    }

    Log "Creating self-signed certificate via PowerShell..."
    try {
        $cert = New-SelfSignedCertificate `
            -Subject "CN=corex.local, O=Corex Development, L=Local, C=US" `
            -DnsName "corex.local", "localhost", "127.0.0.1", "::1" `
            -FriendlyName "Corex Local Development Certificate" `
            -CertStoreLocation "Cert:\LocalMachine\My" `
            -KeyUsage DigitalSignature, KeyEncipherment, DataEncipherment `
            -KeyAlgorithm RSA `
            -KeyLength 2048 `
            -NotAfter (Get-Date).AddYears(5) `
            -TextExtension @("2.5.29.19={text}CA=TRUE", "2.5.29.37={text}1.3.6.1.5.5.7.3.1,1.3.6.1.5.5.7.3.2") `
            -ErrorAction Stop

        $pwd = ConvertTo-SecureString $password -AsPlainText -Force
        Export-PfxCertificate -Cert $cert -FilePath $certPath -Password $pwd -Force | Out-Null
        Export-Certificate -Cert $cert -FilePath $certCerPath -Type CERT -Force | Out-Null

        # Export as PEM for nginx
        $certPemBytes = $cert.RawData
        $certBase64 = [Convert]::ToBase64String($certPemBytes, 'InsertLineBreaks')
        "-----BEGIN CERTIFICATE-----$certBase64-----END CERTIFICATE-----" | Out-File $certPemPath -Encoding ascii

        Ok "Certificate created: $certPath"
    } catch {
        Err "Failed to create certificate via PowerShell: $_"
        throw
    }
}

function Create-CertificateViaOpenSSL {
    Log "Creating self-signed certificate via OpenSSL..."

    $caKey = "$sslDir\ca-key.pem"
    $caCert = "$sslDir\ca-cert.pem"
    $serverKey = "$sslDir\corex-local.key"
    $serverCsr = "$sslDir\corex-local.csr"
    $serverCert = "$sslDir\corex-local.pem"
    $serverPfx = "$sslDir\corex-local.pfx"

    # CA config
    $caConf = @'
[req]
default_bits = 2048
prompt = no
default_md = sha256
x509_extensions = v3_ca
distinguished_name = dn
[dn]
CN = Corex Local CA
O = Corex Development
[ v3_ca ]
basicConstraints = critical, CA:TRUE
keyUsage = critical, keyCertSign, cRLSign
subjectKeyIdentifier = hash
authorityKeyIdentifier = keyid:always,issuer
'@

    # Server config
    $serverConf = @'
[req]
default_bits = 2048
prompt = no
default_md = sha256
distinguished_name = dn
req_extensions = v3_req
[dn]
CN = corex.local
O = Corex Development
[ v3_req ]
basicConstraints = CA:FALSE
keyUsage = nonRepudiation, digitalSignature, keyEncipherment
subjectAltName = @alt_names
[alt_names]
DNS.1 = corex.local
DNS.2 = localhost
IP.1 = 127.0.0.1
IP.2 = ::1
'@

    $caConf | Out-File "$sslDir\ca.conf" -Encoding ascii
    $serverConf | Out-File "$sslDir\server.conf" -Encoding ascii

    # CA key and cert
    Log "Generating CA..."
    openssl genrsa -out $caKey 2048 2>$null
    openssl req -x509 -new -nodes -key $caKey -sha256 -days 3650 -config "$sslDir\ca.conf" -out $caCert 2>$null

    # Server key and cert
    Log "Generating server certificate..."
    openssl genrsa -out $serverKey 2048 2>$null
    openssl req -new -key $serverKey -out $serverCsr -config "$sslDir\server.conf" 2>$null
    openssl x509 -req -in $serverCsr -CA $caCert -CAkey $caKey -CAcreateserial -out $serverCert -days 1825 -sha256 -extensions v3_req -extfile "$sslDir\server.conf" 2>$null

    # Convert to PFX
    $password = "corex-ssl-$(Get-Date -Format 'yyyyMMdd')"
    openssl pkcs12 -export -in $serverCert -inkey $serverKey -out $serverPfx -passout "pass:$password" 2>$null

    Ok "Certificates created via OpenSSL"
}

function Install-CertificateToTrustStore {
    Log "Installing certificate to Trusted Root store..."

    $certFiles = @(
        "$sslDir\ca-cert.pem",
        "$sslDir\corex-local.cer",
        "$sslDir\corex-local.pem"
    )

    $installed = $false
    foreach ($cf in $certFiles) {
        if (-not (Test-Path $cf)) { continue }

        try {
            $cert = New-Object System.Security.Cryptography.X509Certificates.X509Certificate2($cf)
            $store = New-Object System.Security.Cryptography.X509Certificates.X509Store('Root', 'LocalMachine')
            $store.Open('ReadWrite')

            # Check if already trusted
            $existing = $store.Certificates | Where-Object { $_.Thumbprint -eq $cert.Thumbprint }
            if ($existing) {
                Log "Certificate already in Trusted Root: $($cert.Subject)"
            } else {
                $store.Add($cert)
                Ok "Certificate added to Trusted Root: $($cert.Subject)"
                $installed = $true
            }
            $store.Close()
        } catch {
            Warn "Could not install certificate to Trusted Root: $_"
        }
    }

    if (-not $installed) {
        # Try certutil as fallback
        try {
            if (Test-Path "$sslDir\corex-local.cer") {
                certutil -addstore -f Root "$sslDir\corex-local.cer" 2>$null
                Ok "Certificate installed via certutil"
            }
        } catch {
            Warn "Could not install certificate. Manual step:"
            Warn "  Double-click $sslDir\corex-local.cer → Install Certificate → Local Machine → Trusted Root"
        }
    }

    Log "Certificate files in: $sslDir"
    Get-ChildItem $sslDir | ForEach-Object { Log "  $($_.Name) ($([math]::Round($_.Length/1KB,1))KB)" }
}
