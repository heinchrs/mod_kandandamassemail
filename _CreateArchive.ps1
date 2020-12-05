<#
.SYNOPSIS
  Packaging of Joomla module files
.DESCRIPTION
  This script packs all needed files of Joomla module together in a ZIP archive, so that it can be installed in Joomla.
.PARAMETER
  None
.INPUTS
  None
.OUTPUTS
  <Outputs if any, otherwise state None - example: Log file stored in C:\Windows\Temp\<name>.log>
.NOTES
  Version:        1.0
  Author:         Heinl Christian
  Creation Date:  03.12.2020
  Purpose/Change: Initial script development
#>

#----------------------------------------------------------[Declarations]----------------------------------------------------------

#Name of directory containing the current files. This is the name of the extension
$sExtensionName = (dir).directory.name[0]

$sUpdateXMLfilePath = ".release\" + $sExtensionName + "_update.xml"

#-----------------------------------------------------------[Execution]------------------------------------------------------------

#Read content of Joomla extension XML file
$info = [XML] (Get-Content -Path "$sExtensionName.xml")
$sVersionInfo = $info.DocumentElement.SelectNodes("//version").InnerText
#$VersionInfo = $VersionInfo -replace '\.','_'

# target path
$path = "./"
# construct archive path
#$sDateTime = (Get-Date -Format "yyMMdd")
#$sDestination = ".release\archive_" + $VersionInfo + "_" + $DateTime + ".zip"
$sDestination = ".release\" + $sExtensionName + "-v" + $sVersionInfo + ".zip"
# exclusion rules. Can use wild cards (*)
$exclude = @(".release",".vscode",".git","*.ps1")
# get files to compress using exclusion filer
$files = Get-ChildItem -Path $path -Exclude $exclude
# compress
Compress-Archive -Path $files -DestinationPath $sDestination -CompressionLevel Fastest -Force


#Update version info in update XML file
$xml = New-Object XML
$xml.Load($sUpdateXMLfilePath)
$element =  $xml.SelectSingleNode("//version")
$element.InnerText = $sVersionInfo
$xml.Save($sUpdateXMLfilePath)
