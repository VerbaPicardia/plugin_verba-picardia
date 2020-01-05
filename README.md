# plugin_verba-alpina

This WordPress plugin is used on [Verba Picardia](https://anr-appi.univ-lille.fr) site.

It is heavily inspired by [Verba Alpina Plugin](https://github.com/VerbaAlpina/Verba-Alpina-Plugin).

## Requirements

In additional to a standard AMP stack (Apache-Mysql/MariaDB-PHP), some tweaks are needed on the DBMS. The following
configuration file provides them and may be added to Mysql/MariaDB's `conf.d` directory:

    [mysqld]
    lower_case_table_names=1
    group_concat_max_len = 1000000

## Installation

1. Install WordPress.
2. Create the VA working database (`va_xxx`) and fill it with expected tables and data (TODO: minimal set of data including among others stored procedures).
3. Install the [Interactive Map plugin](https://github.com/VerbaAlpina/Interactive-Map_Plugin).
4. Install the [Transcription Tool plugin](https://github.com/VerbaAlpina/TranscriptionTool-Plugin).
5. Install this plugin.
6. Install a compatible theme (you may use [VerbaPicardia's Theme](https://github.com/anr-appi/theme_verba-picardia)
as a starting point).

## Configuration

1. Configure the access to VA databases by creating a file called `login` in this plugin's directory. The file
   should contain the username, password and host (in this order, one per line) for accessing VA databases.
2. Build Interactive Map files
     - Go to `im_config/live` folder
     - Execute `build.php` script (you may have to adapt paths in `build.php`, `compile_all.ant`, `va_externs.xml` and `va_sources.xml`)
3. Add a page including the shortcode `[im_show_map name="VA"]`
