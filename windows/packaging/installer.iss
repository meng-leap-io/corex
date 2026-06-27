; Inno Setup Script for Corex Desktop Application (Self-Contained)
; Build with: ISCC.exe installer.iss
; Requires: Inno Setup 6+ (https://jrsoftware.org/isdl.php)

#define MyAppName "Corex"
#define MyAppVersion "1.0.0"
#define MyAppPublisher "Corex Development"
#define MyAppURL "https://corex.dev"
#define MyAppSupportURL "https://corex.dev/support"
#define MyAppUpdatesURL "https://corex.dev/updates"
#define MyAppExeName "start-corex.bat"
#define MyAppArch "x64"
#define MyAppAssocExt ".corex"
#define MyAppAssocKey "CorexProject"

[Setup]
AppId={{A1B2C3D4-E5F6-7890-ABCD-EF1234567890}
AppName={#MyAppName}
AppVersion={#MyAppVersion}
AppPublisher={#MyAppPublisher}
AppPublisherURL={#MyAppURL}
AppSupportURL={#MyAppSupportURL}
AppUpdatesURL={#MyAppUpdatesURL}
VersionInfoVersion={#MyAppVersion}
DefaultDirName={autopf}\Corex
DefaultGroupName=Corex
AllowNoIcons=yes
DisableProgramGroupPage=no
OutputDir=.\Output
OutputBaseFilename=Corex-Setup-{#MyAppVersion}-{#MyAppArch}
Compression=lzma2/ultra64
SolidCompression=yes
LZMABlockSize=8192
SetupIconFile=..\..\electron\resources\icon.ico
WizardStyle=modern
WizardSizePercent=120
PrivilegesRequired=admin
ArchitecturesInstallIn64BitMode=x64
ArchitecturesAllowed=x64
UninstallDisplayIcon={app}\corex.ico
UninstallDisplayName={#MyAppName} {#MyAppVersion}
ShowLanguageDialog=no
MinVersion=10.0.19045

[Languages]
Name: "english"; MessagesFile: "compiler:Default.isl"

[Types]
Name: "standard"; Description: "Standard installation"
Name: "portable"; Description: "Portable (no registry changes)"
Name: "custom"; Description: "Custom installation"; Flags: iscustom

[Components]
Name: "core"; Description: "Corex Platform (required)"; Types: standard portable custom; Flags: fixed
Name: "runtimes"; Description: "PHP + Python + Nginx + Redis + Node.js runtimes"; Types: standard custom; Flags: checkablealone
Name: "backend"; Description: "Laravel backend application"; Types: standard custom; Flags: checkablealone
Name: "gateway"; Description: "AI Gateway (Python)"; Types: standard custom; Flags: checkablealone
Name: "electron"; Description: "Desktop shell (Electron)"; Types: standard custom; Flags: checkablealone
Name: "desktop"; Description: "Desktop and Start Menu shortcuts"; Types: standard custom; Flags: checkablealone

[InstallDelete]
Type: filesandordirs; Name: "{app}\app\backend\storage\logs"
Type: filesandordirs; Name: "{app}\app\backend\vendor\bin"
Type: filesandordirs; Name: "{app}\app\ai-gateway\__pycache__"
Type: filesandordirs; Name: "{app}\app\ai-gateway\.mypy_cache"

[Dirs]
Name: "{app}"; Permissions: users-modify
Name: "{app}\app"
Name: "{app}\data"; Permissions: users-full
Name: "{app}\data\db"
Name: "{app}\data\cache"
Name: "{app}\data\pids"
Name: "{app}\logs"; Permissions: users-full
Name: "{localappdata}\Corex"; Permissions: users-full
Name: "{localappdata}\Corex\cache"
Name: "{localappdata}\Corex\logs"
Name: "{localappdata}\Corex\database"

[Files]
; ── Root ──────────────────────────────────────────────────────────────
Source: "..\..\LICENSE"; DestDir: "{app}"; Flags: ignoreversion
Source: "..\..\README.md"; DestDir: "{app}"; Flags: ignoreversion

; ── Portable Launchers ────────────────────────────────────────────────
Source: "portable\start-corex.bat"; DestDir: "{app}"; Flags: ignoreversion; Components: core
Source: "portable\stop-corex.bat"; DestDir: "{app}"; Flags: ignoreversion; Components: core
Source: "portable\start-corex.ps1"; DestDir: "{app}"; Flags: ignoreversion; Components: core

; ── Backend Application ──────────────────────────────────────────────
Source: "..\..\backend\*"; DestDir: "{app}\app\backend"; Flags: ignoreversion recursesubdirs createallsubdirs; Components: backend; Excludes: "node_modules,__pycache__,.git,storage/logs/*,storage/framework/cache/*,storage/framework/sessions/*,storage/framework/views/*,tests"

; ── AI Gateway ────────────────────────────────────────────────────────
Source: "..\..\ai-gateway\*"; DestDir: "{app}\app\ai-gateway"; Flags: ignoreversion recursesubdirs createallsubdirs; Components: gateway; Excludes: "__pycache__,.venv,.mypy_cache,node_modules,tests,.git"

; ── Runtimes (from build directory) ──────────────────────────────────
Source: "..\..\build\runtime\php\*"; DestDir: "{app}\php"; Flags: ignoreversion recursesubdirs; Components: runtimes
Source: "..\..\build\runtime\python\*"; DestDir: "{app}\python"; Flags: ignoreversion recursesubdirs; Components: runtimes
Source: "..\..\build\runtime\nginx\*"; DestDir: "{app}\nginx"; Flags: ignoreversion recursesubdirs; Components: runtimes
Source: "..\..\build\runtime\redis\*"; DestDir: "{app}\redis"; Flags: ignoreversion recursesubdirs; Components: runtimes
Source: "..\..\build\runtime\nodejs\*"; DestDir: "{app}\nodejs"; Flags: ignoreversion recursesubdirs; Components: runtimes
Source: "..\..\build\runtime\nssm\*"; DestDir: "{app}\nssm"; Flags: ignoreversion recursesubdirs; Components: runtimes

; ── Nginx Configuration ──────────────────────────────────────────────
Source: "..\..\docker\nginx\*"; DestDir: "{app}\nginx\conf"; Flags: ignoreversion recursesubdirs; Components: core

; ── Electron Shell ────────────────────────────────────────────────────
Source: "..\..\electron\*"; DestDir: "{app}\electron"; Flags: ignoreversion recursesubdirs; Components: electron; Excludes: "node_modules,.git,dist"

; ── Windows Service Scripts ──────────────────────────────────────────
Source: "..\..\windows\native-services\*"; DestDir: "{app}\services"; Flags: ignoreversion recursesubdirs; Components: core
Source: "..\*.ps1"; DestDir: "{app}\scripts"; Flags: ignoreversion; Components: core
Source: "..\*.bat"; DestDir: "{app}\scripts"; Flags: ignoreversion; Components: core
Source: "..\native-services\CorexServiceHost\bin\Release\net8.0\publish\*"; DestDir: "{app}\services\host"; Flags: ignoreversion recursesubdirs; Components: core

; ── Update Manager ────────────────────────────────────────────────────
Source: "..\installer\updates\Update-Corex.ps1"; DestDir: "{app}\scripts"; Flags: ignoreversion; Components: core

; ── Icons ─────────────────────────────────────────────────────────────
Source: "..\..\electron\resources\icon.ico"; DestDir: "{app}"; Flags: ignoreversion; Components: core
Source: "..\..\electron\resources\*.png"; DestDir: "{app}\resources"; Flags: ignoreversion; Components: core

[Icons]
Name: "{desktop}\Corex"; Filename: "{app}\start-corex.bat"; IconFilename: "{app}\icon.ico"; WorkingDir: "{app}"; Comment: "Corex AI Development Platform"; Components: desktop
Name: "{desktop}\Corex (Portable Mode)"; Filename: "{app}\start-corex.bat"; IconFilename: "{app}\icon.ico"; WorkingDir: "{app}"; Parameters: "-Portable"; Components: desktop
Name: "{group}\Corex"; Filename: "{app}\start-corex.bat"; IconFilename: "{app}\icon.ico"; WorkingDir: "{app}"
Name: "{group}\Corex Manager"; Filename: "{app}\scripts\menu.bat"; IconFilename: "{app}\resources\tray-icon.png"; WorkingDir: "{app}"
Name: "{group}\Stop Corex"; Filename: "{app}\stop-corex.bat"; IconFilename: "{app}\icon.ico"
Name: "{group}\View Logs"; Filename: "{app}\logs"; IconFilename: "{app}\icon.ico"
Name: "{group}\Check for Updates"; Filename: "powershell.exe"; Parameters: "-ExecutionPolicy Bypass -NoProfile -File ""{app}\scripts\Update-Corex.ps1"""; WorkingDir: "{app}"
Name: "{group}\{cm:UninstallProgram,Corex}"; Filename: "{uninstallexe}"
Name: "{userstartup}\Corex"; Filename: "{app}\start-corex.bat"; WorkingDir: "{app}"; Components: desktop

[Run]
; Nginx requires administrative privileges for port 80
Filename: "{app}\start-corex.bat"; Description: "Launch Corex"; Flags: postinstall skipifsilent nowait; WorkingDir: "{app}"

; Register protocol handler
Filename: "powershell.exe"; Parameters: "-ExecutionPolicy Bypass -NoProfile -Command ""New-Item -Path 'HKLM:\SOFTWARE\Classes\corex' -Force | New-ItemProperty -Name 'URL Protocol' -Value '' -Force | Out-Null"""; Flags: runhidden; Components: core

[UninstallRun]
Filename: "powershell.exe"; Parameters: "-ExecutionPolicy Bypass -NoProfile -File ""{app}\stop-corex.bat"""; RunOnceId: "StopServices"; Components: core
Filename: "powershell.exe"; Parameters: "-ExecutionPolicy Bypass -NoProfile -Command ""Remove-Item -Path 'HKLM:\SOFTWARE\Classes\corex' -Recurse -Force -ErrorAction SilentlyContinue"""; Flags: runhidden; Components: core

[Registry]
Root: HKLM; Subkey: "Software\Corex"; Flags: uninsdeletekey
Root: HKLM; Subkey: "Software\Corex"; ValueType: string; ValueName: "InstallPath"; ValueData: "{app}"
Root: HKLM; Subkey: "Software\Corex"; ValueType: string; ValueName: "Version"; ValueData: "{#MyAppVersion}"
Root: HKLM; Subkey: "Software\Corex"; ValueType: string; ValueName: "DisplayName"; ValueData: "{#MyAppName}"
Root: HKLM; Subkey: "Software\Corex"; ValueType: string; ValueName: "Publisher"; ValueData: "{#MyAppPublisher}"
Root: HKLM; Subkey: "Software\Corex"; ValueType: string; ValueName: "InstallDate"; ValueData: "{code:GetInstallDate}"

; Architecture
Root: HKLM; Subkey: "Software\Corex"; ValueType: string; ValueName: "Architecture"; ValueData: "{#MyAppArch}"

; Protocol handler
Root: HKCR; Subkey: "corex"; ValueType: string; ValueData: "URL:Corex Protocol"; Flags: uninsdeletekey
Root: HKCR; Subkey: "corex\DefaultIcon"; ValueType: string; ValueData: "{app}\icon.ico"
Root: HKCR; Subkey: "corex\shell\open\command"; ValueType: string; ValueData: """{app}\start-corex.bat"" ""%1"""

; File association
Root: HKCR; Subkey: "{#MyAppAssocExt}"; ValueType: string; ValueData: "{#MyAppAssocKey}"; Flags: uninsdeletekey
Root: HKCR; Subkey: "{#MyAppAssocKey}"; ValueType: string; ValueData: "Corex Project File"
Root: HKCR; Subkey: "{#MyAppAssocKey}\DefaultIcon"; ValueType: string; ValueData: "{app}\icon.ico"
Root: HKCR; Subkey: "{#MyAppAssocKey}\shell\open\command"; ValueType: string; ValueData: """{app}\start-corex.bat"" ""%1"""

; Environment variable
Root: HKCU; Subkey: "Environment"; ValueType: expandsz; ValueName: "COREX_HOME"; ValueData: "{app}"; Flags: createvalueifdoesntexist deletevalueoninstall

; Auto-start
Root: HKCU; Subkey: "Software\Microsoft\Windows\CurrentVersion\Run"; ValueType: string; ValueName: "Corex"; ValueData: """{app}\start-corex.bat"""; Flags: uninsdeletevalue

[Code]
var
  InstallDate: string;

function GetInstallDate(Param: string): string;
begin
  Result := GetDateTimeString('yyyymmdd', '-', ':');
end;

function IsDotNetInstalled: Boolean;
var
  dotNetVersion: Cardinal;
begin
  Result := RegQueryDWordValue(HKLM, 'SOFTWARE\Microsoft\NET Framework Setup\NDP\v4\Full', 'Release', dotNetVersion);
  if Result then
    Result := dotNetVersion >= 528040;  // .NET 8.0+
end;

function InitializeSetup: Boolean;
var
  memMB: Cardinal;
  osBuild: Cardinal;
begin
  Result := True;

  // Windows 10 21H2+ (build 19045) or newer
  osBuild := GetWindowsBuildNumber;
  if osBuild < 19045 then begin
    SuppressibleMsgBox('Windows 10 version 21H2 (build 19045) or later is required.'#13 +
      'Current build: ' + IntToStr(osBuild), mbCriticalError, MB_OK);
    Result := False;
  end;

  // 8GB RAM minimum
  memMB := GetPhysicalMemorySizeInMB;
  if memMB < 8192 then begin
    if SuppressibleMsgBox('Minimum 8GB of RAM recommended.'#13 +
      'Current: ' + IntToStr(memMB div 1024) + ' GB.'#13 +
      'Continue anyway?', mbConfirmation, MB_YESNO) = IDNO then
      Result := False;
  end;

  // 2GB free disk space minimum
  if GetSpaceOnDisk(ExpandConstant('{autopf}'), True) < 2048 then begin
    SuppressibleMsgBox('Insufficient disk space. At least 2GB free required.', mbCriticalError, MB_OK);
    Result := False;
  end;
end;

procedure CurStepChanged(CurStep: TSetupStep);
var
  ResultCode: Integer;
  ScriptPath: String;
  PowershellPath: String;
begin
  if CurStep = ssPostInstall then begin
    // Configure Windows service if C# host is bundled
    ScriptPath := ExpandConstant('{app}\services\install-service.bat');
    if FileExists(ScriptPath) then begin
      PowershellPath := ExpandConstant('{sys}\WindowsPowerShell\v1.0\powershell.exe');
      Exec(PowershellPath,
        '-ExecutionPolicy Bypass -NoProfile -File "' + ScriptPath + '"',
        ExpandConstant('{app}'),
        SW_HIDE, ewNoWait, ResultCode);
    end;
  end;
end;

procedure CurUninstallStepChanged(CurUninstallStep: TUninstallStep);
begin
  if CurUninstallStep = usPostUninstall then begin
    // Remove user data
    if MsgBox('Remove all user data and settings?', mbConfirmation, MB_YESNO) = IDYES then begin
      DelTree(ExpandConstant('{localappdata}\Corex'), True, True, True);
    end;
  end;
end;
