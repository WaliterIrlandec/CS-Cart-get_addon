# CS-Cart Get Add-on

### Description
Pack all the add-on files in a folder


### Code Example
##### Browser
Example URL: http://%DOMAIN_NAME%/get_addon.php?addon_name=%ADDON_ID%&package&zip

##### Console
php get_addon.php --addon_name=addon_id --package='folder_name'


### Installation
Copy get_addon.php in the CS-Cart root directory


### Reference
The copied files are located in the 'get_addons/addon_id/folder_name' folder.
##### Folder name
The name is formed as follows:

- default                   - "add-on id + date"
- empty package param       - "add-on id + add-on version"
- specified package param   - "package param"

#### Params

##### Browser
Request params:

    help          Show info
    wibug         Display PHP notice
    addon_name    List of the add-ons separated by comma
    package       Create folder with the add-on version or specified name
    zip           Create zip archive
    upload        Upload zip archive

##### Console
Options:

            --help          Show info
            --wibug         Display PHP notice
        -a  --addon_name    List of the add-ons separated by comma
        -p  --package       Create folder on package format with the specified name or (if not exist) the add-on version
        -z  --zip           Create zip archive

### License
GNU General Public License 3.0
