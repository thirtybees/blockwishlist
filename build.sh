#!/usr/bin/env bash
CWD_BASENAME=${PWD##*/}

FILES+=("blockwishlist.css")
FILES+=("${CWD_BASENAME}.php")
FILES+=("blockwishlist.tpl")
FILES+=("blockwishlist-ajax.tpl")
FILES+=("blockwishlist-extra.tpl")
FILES+=("blockwishlist_button.tpl")
FILES+=("blockwishlist_top.tpl")
FILES+=("buywishlistproduct.php")
FILES+=("cart.php")
FILES+=("index.php")
FILES+=("install.sql")
FILES+=("logo.gif")
FILES+=("logo.png")
FILES+=("managewishlist.php")
FILES+=("my-account.tpl")
FILES+=("Readme.md")
FILES+=("sendwishlist.php")
FILES+=("classes/**")
FILES+=("controllers/**")
FILES+=("img/**")
FILES+=("js/**")
FILES+=("mails/**")
FILES+=("translations/**")
FILES+=("upgrade/**")
FILES+=("views/**")

MODULE_VERSION="$(sed -ne "s/\\\$this->version *= *['\"]\([^'\"]*\)['\"] *;.*/\1/p" ${CWD_BASENAME}.php)"
MODULE_VERSION=${MODULE_VERSION//[[:space:]]}
ZIP_FILE="${CWD_BASENAME}/${CWD_BASENAME}-v${MODULE_VERSION}.zip"

echo "Going to zip ${CWD_BASENAME} version ${MODULE_VERSION}"

cd ..
for E in "${FILES[@]}"; do
  find ${CWD_BASENAME}/${E}  -type f -exec zip -9 ${ZIP_FILE} {} \;
done
