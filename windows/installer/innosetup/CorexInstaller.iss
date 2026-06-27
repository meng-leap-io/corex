; Inno Setup Script for Corex AI Development Platform
; 
; Download Inno Setup from: https://www.jrsoftware.org/isdl.php
; 
; Build instructions:
;   1. Install Inno Setup
;   2. Open this file in Inno Setup
;   3. Click "Build" -> "Compile"
;   4. Output: Output\CorexSetup-1.0.0.exe
;
; Scripting guide: https://jrsoftware.org/ishelp/

#define MyAppName "Corex"
#define MyAppVersion "1.0.0"
#define MyAppPublisher "Corex Development"
#define MyAppURL "https://corex.dev"
#define MyAppExeName "corex-launcher.exe"
#define MyAppAssociation ".corex"

[Setup]
AppId={{550E8400-E29B-41D4-A716-446655440000}
AppName={#MyAppName}
AppVersion={#MyAppVersion}
AppPublisher={#MyAppPublisher}
AppPublisherURL={#MyAppURL}
AppSupportURL={#MyAppURL}/support
AppUpdatesURL={#MyAppURL}/updates
DefaultDirName={autopf}\Corex
DefaultGroupName=Corex
AllowNoIcons=yes
DisableProgramGroupPage=no
LicenseFile=..\..\LICENSE
InfoBeforeFile=..\..\docs\INSTALL_NOTES.txt
OutputDir=.\Output
OutputBaseFilename=CorexSetup-{#MyAppVersion}
Compression=lzma2
SolidCompression=yes
SetupIconFile=assets\installer.ico
WizardStyle=modern
WizardImageFile=assets\wizard-image.bmp
WizardSmallImageFile=assets\wizard-small-image.bmp
PrivilegesRequired=admin
ArchitecturesInstallIn64BitMode=x64
ArchitecturesAllowed=x64

; Uninstaller settings
UninstallDisplayIcon={app}\{#MyAppExeName}
UninstallDisplayName=Uninstall {#MyAppName}

; Languages
[Languages]
Name: "english"; MessagesFile: "compiler:Default.isl"
Name: "french"; MessagesFile: "compiler:Languages\French.isl"
Name: "german"; MessagesFile: "compiler:Languages\German.isl"
Name: "spanish"; MessagesFile: "compiler:Languages\Spanish.isl"

; Custom page components
[CustomMessages]
english.InstallDocker=Docker Desktop installation required
english.InstallPHP=PHP 8.3+ installation required
english.InstallPython=Python 3.12+ installation required
french.InstallDocker=Installation de Docker Desktop requise
german.InstallDocker=Docker Desktop-Installation erforderlich
spanish.InstallDocker=Se requiere instalación de Docker Desktop

[Types]
Name: "full"; Description: "Full installation"
Name: "compact"; Description: "Compact installation"
Name: "minimal"; Description: "Minimal installation"
Name: "custom"; Description: "Custom installation"; Flags: iscustom

[Components]
Name: "core"; Description: "Corex Core (Laravel backend, FastAPI gateway, Nginx)"; Types: full compact minimal custom; Flags: fixed
Name: "docker"; Description: "Docker Desktop integration"; Types: full custom; Flags: checkablealone
Name: "php"; Description: "PHP 8.3+"; Types: full custom
Name: "python"; Description: "Python 3.12+"; Types: full custom
Name: "redis"; Description: "Redis for caching"; Types: full custom
Name: "ollama"; Description: "Ollama for local AI models (optional)"; Types: full custom
Name: "tools"; Description: "Utilities and tools"; Types: full custom
Name: "desktop"; Description: "Desktop shortcuts"; Types: full compact custom; Flags: checkablealone
Name: "tasks"; Description: "Windows Task Scheduler integration"; Types: full custom

[Dirs]
Name: "{app}"; Flags: uninsalwaysuninstall
Name: "{app}\backend"
Name: "{app}\ai-gateway"
Name: "{app}\nginx"
Name: "{app}\data"
Name: "{app}\logs"
Name: "{app}\scripts"
Name: "{localappdata}\Corex"; Flags: uninsalwaysuninstall
Name: "{localappdata}\Corex\cache"
Name: "{localappdata}\Corex\database"

[Files]
; Core application files
Source: "..\..\backend\*"; DestDir: "{app}\backend"; Flags: ignoreversion recursesubdirs; Components: core
Source: "..\..\ai-gateway\*"; DestDir: "{app}\ai-gateway"; Flags: ignoreversion recursesubdirs; Components: core
Source: "..\..\docker\nginx\*"; DestDir: "{app}\nginx"; Flags: ignoreversion recursesubdirs; Components: core
Source: "..\..\docker-compose.yml"; DestDir: "{app}"; Flags: ignoreversion; Components: core
Source: "..\..\README.md"; DestDir: "{app}"; Flags: ignoreversion; Components: core
Source: "..\..\LICENSE"; DestDir: "{app}"; Flags: ignoreversion; Components: core
Source: "..\..\docs\*"; DestDir: "{app}\docs"; Flags: ignoreversion recursesubdirs; Components: core

; Scripts
Source: "..\*.ps1"; DestDir: "{app}\scripts"; Flags: ignoreversion; Components: tools
Source: "..\*.bat"; DestDir: "{app}\scripts"; Flags: ignoreversion; Components: tools
Source: "scripts\*"; DestDir: "{app}\scripts"; Flags: ignoreversion recursesubdirs; Components: tools

; Installation scripts
Source: "scripts\Install-Dependencies.ps1"; DestDir: "{app}\scripts"; Flags: ignoreversion; Components: tools
Source: "scripts\Configure-Services.ps1"; DestDir: "{app}\scripts"; Flags: ignoreversion; Components: core

; Launcher executable (runs in background)
Source: "assets\corex-launcher.exe"; DestDir: "{app}"; Flags: ignoreversion; Components: core

; Icons
Source: "assets\*.ico"; DestDir: "{app}"; Flags: ignoreversion; Components: core

[Icons]
; Desktop shortcut
Name: "{desktop}\Corex"; Filename: "http://localhost"; IconFilename: "{app}\corex.ico"; Components: desktop
Name: "{desktop}\Corex Manager"; Filename: "{app}\scripts\menu.bat"; IconFilename: "{app}\corex-manager.ico"; WorkingDir: "{app}\scripts"; Components: desktop

; Start menu shortcuts
Name: "{group}\Corex"; Filename: "http://localhost"; IconFilename: "{app}\corex.ico"
Name: "{group}\Corex Manager"; Filename: "{app}\scripts\menu.bat"; IconFilename: "{app}\corex-manager.ico"; WorkingDir: "{app}\scripts"
Name: "{group}\Documentation"; Filename: "notepad.exe"; Parameters: "{app}\README.md"
Name: "{group}\View Logs"; Filename: "{app}\scripts\logs.bat"; WorkingDir: "{app}\scripts"
Name: "{group}\Status Check"; Filename: "{app}\scripts\status.bat"; WorkingDir: "{app}\scripts"
Name: "{group}\{cm:UninstallProgram,{#MyAppName}}"; Filename: "{uninstallexe}"

[Run]
; Compatibility test before installation
Filename: powershell.exe; Parameters: "-ExecutionPolicy Bypass -NoProfile -File ""{app}\scripts\test-compatibility.ps1"""; Description: "Run compatibility test"; Flags: runhidden waituntilterminated; Components: tools

; Post-install configuration
Filename: powershell.exe; Parameters: "-ExecutionPolicy Bypass -NoProfile -File ""{app}\scripts\Configure-Services.ps1"""; StatusMsg: "Configuring services..."; Components: core

; Start Corex Manager
Filename: "{app}\scripts\menu.bat"; Description: "Launch Corex Manager"; Flags: postinstall skipifsilent; Components: core

; Open documentation
Filename: "notepad.exe"; Parameters: "{app}\README.md"; Description: "View documentation"; Flags: postinstall skipifsilent unchecked

[UninstallRun]
; Stop services before uninstall
Filename: powershell.exe; Parameters: "-ExecutionPolicy Bypass -NoProfile -File ""{app}\scripts\CorexServiceWrapper.ps1"" -Action Stop"; RunOnce: yes; Components: core

[InstallDelete]
; Remove installation directory
Name: "{app}"; Type: filesandsubdirs

[Registry]
; Application registry entries
Root: HKLM; Subkey: "Software\Corex"; ValueType: string; ValueName: "InstallPath"; ValueData: "{app}"; Flags: uninsdeletekey
Root: HKLM; Subkey: "Software\Corex"; ValueType: string; ValueName: "Version"; ValueData: "{#MyAppVersion}"
Root: HKLM; Subkey: "Software\Corex"; ValueType: string; ValueName: "DisplayName"; ValueData: "{#MyAppName}"
Root: HKLM; Subkey: "Software\Corex"; ValueType: string; ValueName: "Publisher"; ValueData: "{#MyAppPublisher}"

; File association
Root: HKCR; Subkey: "{#MyAppAssociation}"; ValueType: string; ValueData: "Corex Project File"; Flags: uninsdeletekey
Root: HKCR; Subkey: "{#MyAppAssociation}\DefaultIcon"; ValueType: string; ValueData: "{app}\corex.ico,0"

; Environment variables
Root: HKCU; Subkey: "Environment"; ValueType: string; ValueName: "COREX_HOME"; ValueData: "{app}"; Flags: createvalueifdoesntexist

; Startup with Windows (optional via environment variable)
Root: HKCU; Subkey: "Software\Microsoft\Windows\CurrentVersion\Run"; ValueType: string; ValueName: "Corex"; ValueData: "{app}\scripts\start.bat"; Flags: uninsdeletevalue

[Code]
// Check prerequisites before installation
function InitializeSetup(): Boolean;
var
  DockerInstalled: Boolean;
  PHPVersion: String;
  PythonVersion: String;
  DependenciesMet: Boolean;
begin
  Result := True;
  DependenciesMet := True;

  // Check for Docker Desktop
  if not RegKeyExists(HKLM, 'Software\Docker Inc\Docker') then
  begin
    SuppressibleMsgBox('Docker Desktop is required but not installed.' + #13 +
      'Download from: https://www.docker.com/products/docker-desktop', mbInformation, MB_OK);
    DependenciesMet := False;
  end;

  // Check for minimum Windows version (Windows 10 build 21H2+)
  if not IsWindowsVersionOrNewer(10, 0, 19045) then
  begin
    SuppressibleMsgBox('Windows 10 version 21H2 or later (build 19045+) is required.' + #13 +
      'Please update Windows before installing Corex.', mbInformation, MB_OK);
    Result := False;
    Exit;
  end;

  // Check for minimum RAM (8GB)
  if (TotalPhysicalMemory / 1024 / 1024 / 1024) < 8 then
  begin
    SuppressibleMsgBox('At least 8GB of RAM is required (16GB recommended).' + #13 +
      'Current system has insufficient memory.', mbInformation, MB_OK);
    DependenciesMet := False;
  end;

  Result := True;
end;

// Custom install process
procedure CurStepChanged(CurStep: TSetupStep);
var
  PowerShellPath: String;
  ResultCode: Integer;
begin
  if CurStep = ssInstall then
  begin
    // Install dependencies
    if FileExists(ExpandConstant('{app}\scripts\Install-Dependencies.ps1')) then
    begin
      PowerShellPath := ExpandConstant('{sys}\WindowsPowerShell\v1.0\powershell.exe');
      if FileExists(PowerShellPath) then
      begin
        Exec(PowerShellPath,
          '-ExecutionPolicy Bypass -NoProfile -File "' + ExpandConstant('{app}\scripts\Install-Dependencies.ps1') + '"',
          ExpandConstant('{app}'),
          SW_HIDE, ewWaitUntilTerminated, ResultCode);
      end;
    end;
  end;

  if CurStep = ssPostInstall then
  begin
    // Configure services
    if FileExists(ExpandConstant('{app}\scripts\Configure-Services.ps1')) then
    begin
      PowerShellPath := ExpandConstant('{sys}\WindowsPowerShell\v1.0\powershell.exe');
      if FileExists(PowerShellPath) then
      begin
        Exec(PowerShellPath,
          '-ExecutionPolicy Bypass -NoProfile -File "' + ExpandConstant('{app}\scripts\Configure-Services.ps1') + '"',
          ExpandConstant('{app}'),
          SW_HIDE, ewWaitUntilTerminated, ResultCode);
      end;
    end;
  end;
end;

// Uninstall confirmation
procedure CurUninstallStepChanged(CurUninstallStep: TUninstallStep);
begin
  if CurUninstallStep = usUninstall then
  begin
    if MsgBox('Stop running Corex services?', mbConfirmation, MB_YESNO or MB_DEFBUTTON1) = IDYES then
    begin
      // Services will be stopped via UninstallRun
    end;
  end;
end;

// Log installation details
procedure DeinitializeSetup();
begin
  if not WizardSilent() then
  begin
    SaveStringToFile(ExpandConstant('{app}\install.log'),
      'Installation completed at ' + DateTimeToStr(Now()) + #13#10 +
      'Version: {#MyAppVersion}' + #13#10 +
      'Install Path: ' + ExpandConstant('{app}') + #13#10, False);
  end;
end;
