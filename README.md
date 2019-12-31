# plugin_verba-alpina

This WordPress plugin is used on [Verba Picardia](https://anr-appi.univ-lille.fr) site.

It is heavily inspired by [Verba Alpina Plugin](https://github.com/VerbaAlpina/Verba-Alpina-Plugin).

# Interactive Map Configuration

The [Verba Alpina Interactive Map Plugin](https://github.com/VerbaAlpina/Interactive-Map_Plugin) requires an access
to the database containing information to display.

- Add `login` file in plugin directory with the following format:

    db_username
    db_password
    db_host

- Add page with the following content (shortcode for the inclusion of the interactive map):

    [im_show_map name="VA"]
