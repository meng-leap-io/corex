; Corex NSIS Installer Script
; Included by electron-builder's NSIS

!macro customInstall
  ; Create registry entries for protocol handler (corex://)
  WriteRegStr HKCR "corex" "" "URL:Corex Protocol"
  WriteRegStr HKCR "corex" "URL Protocol" ""
  WriteRegStr HKCR "corex\DefaultIcon" "" "$INSTDIR\Corex.exe"
  WriteRegStr HKCR "corex\shell\open\command" "" '"$INSTDIR\Corex.exe" "%1"'

  ; Create logs directory
  CreateDirectory "$APPDATA\Corex\logs"

  ; Create workspace directory
  CreateDirectory "$APPDATA\Corex\workspace"

  ; Add firewall exception if needed
  ; (omitted - Windows will prompt on first launch)
!macroend

!macro customUnInstall
  ; Remove registry entries
  DeleteRegKey HKCR "corex"

  ; Prompt to remove user data
  MessageBox MB_YESNO|MB_ICONQUESTION \
    "Remove all user data and settings?" \
    /SD IDNO IDNO done
    RMDir /r "$APPDATA\Corex"
  done:
!macroend

; Silent install customization
!macro customInit
  ; Check if running as admin
  UserInfo::GetAccountType
  Pop $0
  ${If} $0 != "admin"
    MessageBox MB_OK|MB_ICONSTOP "Administrator privileges required."
    Abort
  ${EndIf}
!macroend
