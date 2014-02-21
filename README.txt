Show Your Places (SYP) is a web CMS whose goal is to manage a pictures or
photos collection with geographic location. The website shows a map with
markers matching picture locations. When selecting a marker, visitors can see
image and its description.

Requirements
------------
- php5
- mysql (or postgresql) extension for php
- If mysql is used, it must support spatial extension; mysql version must be >=
  4.1 for MyISAM tables or >= 5.0.16 for other tables.
- If postgresql is used, version must be at least 7.4

Installation
------------

* manual configuration

 - edit `inc/settings.php.in` and copy it to `inc/settings.php`
 - upload syp to your webserver
 - open http://yoururl.com/install.php with your web browser to finalize
  installation (database tables creation).

* automatic configuration
 - upload syp to your webserver
 - open http://yoururl.com/install.php with your web browser and follow
  instructions.

For automatic configuration to work, `inc/` directory needs to be writeable. Use
`chmod` to set the appropriate directory permissions. If in doubt, `chmod` to
`0777`. Alternatively, you can use manual method.

Some advanced configuration options are not available with automatic method. If
you need to set those options, use manual configuration.

Upgrade
------------
- uncompress new version of syp
- upload uncompressed version to your server
- open http://yoururl.com/upgrade.php with your web browser

co-administrators
-----------------
It is possible to allow other people to upload and manage
pictures/descriptions. In admin interface, select "Add an co-administrator"
and fill informations (user name and password). Then, you need to communicate
to your user its username and password. He/She will be able to modify this
password afterward. Only admin can add new users.

Other co-administrators will be able to add markers, and delete/modifiy them.
They cannot modify markers they have not created. admin is the only user
allowed to manage markers of other users. If you plan to have several
co-administrators, you may want to create a normal user for yourself, and use
it to manage your markers. You will then only use admin account when really
needed.

Currently, SYP does not allow  

Custom CSS
----------
You can define your own css rules by creating custom css files:
- `media/syp_custom.css` for user interface
- `media/admin_custom.css` for admin interface
Those files are sourced after all other css files. So, rules defined in those
custom files will override other rules.

server API
----------
Client/server communication follows an API. This allow for example creation of
scripts to automate some tasks. [API description] [1] is mainly aimed at
developers and inquiring people. You should not need it if you just want to
install syp on your website.

[1]: http://dev.renevier.net/syp.git/?a=blob_plain;f=devdoc/api.txt


Author
------
Arno Renevier <arno@renevier.net>

Contributor(s):
---------------
    Sebastian Klemm <osm /at/ erlkoenigkabale /dot/ eu> (german translation)
