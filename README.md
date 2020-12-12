# mod_kandandamassemail

## Description

This project builds a module called **Kandanda Mass Email** for the content management system [Joomla](https://www.joomla.org/).

The module requires in Joomla an installation of the <a href="https://www.kandanda.net/" target="_blank">Kandanda-Component</a>.

Using this module, it is possible to send emails to Kandanda members assigned to specific Kandanda groups, or where appropriate Kandanda field values are assigned to.

## Folder structure
- .release -> contains the installation archive of joomla module and the corresponding update XML
- .vscode -> contains the VSCode task file for creating joomla archive
- language -> contains the language specific translations
- tmpl -> contains the module template which generates the HTML to be displayed on the page

## Joomla packaging
For packaging the Joomla module a powershell script _CreateArchive.ps1 exists. This script packs all needed files into a zip archive and updates the version information in the update XML file corresponding to the version information in the module manifest file mod_kandandamassemail.xml

