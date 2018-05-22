# MyBB Image Hider
## Table of Contents

 1. What is Image Hider
 2. Installation
 3. Configuration
 4. Contribution

## What is Image Hider
Image Hider is a plugin for the php forum software MyBB. It's easy to install and provides some conifguration options. The main feature of Image Hider is to hide images which are hosted on not whitelisted domains.
All featrues are listed in the enumeration below:
1. Hide iamges from not whitelisted hosts
2. Set a replacement code for blocked images in `<img>`-tags
3. Hide images which are not served via https
4. Define a replacement image for not `img`-tags
5. Exclude pages from image hiding

## Installation
Simply upload the `image-hider.php` file to your webserver and put it into the `inc/plugins/` folder. You could also use the upload plugin function of the MyBB webinterface.

## Configuration

Image Hider provides the following configurations. They can be found by navigation to `acp` -> `configuration` -> `settings` -> `Image Hider`.

 1. **Activated**: Indicates if hiding images is active or not. Dafault is `on`.
 2. **Semicolon sperated list of whitelisted URLs**: A list of semicolon separated URLs which are whitelisted for displaying the image. Please do not use any whitespaces. Remember to whitelist your own website. E.g. `tumblr.com;imgur.com;`. Default is `$_SERVER['HTTP_HOST']` aka. the current domain. 
 3. **The text the matched images will be replaced with**: Defines what the found images will be replaced with. You can use HTML but short code is recommended. Use {src} for the original URL to the image. Default is `<span>[Image: <a href="{src}">{src}</a>]</span>`. There is no default value.
 4. **The replacement image which will be displayed if linking is not possible**: Defines a URL to the replacement image if the linking of the image is not possible e.g. in CSS. Be sure to whitelist the url of the replacement image.
 5. **A list of files which will be excluded**: Defines a semicolon separated list of files which will be ignored by this plugin. There is no default value.
 6. **Block http image urls**: Indicates if images served over http will be hidden. It will only be blocked if the URL contains `http`. Default is `on`.

## Contribution
If you want to contribute to this plugin you can simply create a [pull-request](https://help.github.com/articles/about-pull-requests/). I can't promise to check for pull-requests daily but I'll try to. 
If you want to report a bug feel free to create a new [issue](https://github.com/Implex1v/MyBBImageHider/issues).