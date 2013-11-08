# VersionPress #

## Prerequisites ##
Apache webserver
Git

## Requirements ##
PHP version 5.3 or greater
MySQL version 5.0 or greater

## Installation ##

1. Copy files into `wp-content` directory.
2. Configure Apache
3. Activate plugin in WordPress administration
4. Enjoy!


## Apache Configuration Example ##
httpd.conf
```
# ============================================================================
### Git Configuration
# ============================================================================

<Directory "${path}/www"> # ${path} is special EasyPHP variable – it should be replaced with real path
Options +ExecCGI
Require all granted
</Directory>

SetEnv GIT_PROJECT_ROOT ${path}/www
SetEnv GIT_HTTP_EXPORT_ALL
SetEnv REMOTE_USER=$REDIRECT_REMOTE_USER

ScriptAliasMatch "(?x)^/(.*/(HEAD|info/refs|objects/(info/[^/]+|[0-9a-f]{2}/[0-9a-f]{38}|pack/pack-[0-9a-f]{40}\.(pack|idx))|git-(upload|receive)-pack))$" "c:/Program Files (x86)/Git/libexec/git-core/git-http-backend.exe/$1" # Set path to git-http-backend

<LocationMatch ".*\.git.*">
Options +ExecCGI
AuthType Basic
AuthUserFile "${path}/www/git/test.git/.htpasswd" # Set path to htpasswd file
AuthName intranet
Require valid-user
</LocationMatch>

# ============================================================================
### Git Configuration End
# ============================================================================
```