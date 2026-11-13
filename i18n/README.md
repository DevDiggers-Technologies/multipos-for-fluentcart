# Languages

## Generating POT

The generated POT template file is included in this directory. To create this file locally, follow instructions to install the project, then run the following command:

```
npm run i18n
```

After the build completes, you'll find a `devdiggers-multipos-for-fluentcart.pot` strings file in this directory.

## Translating POT

To generate PO and MO files from the generated `devdiggers-multipos-for-fluentcart.pot`, you can use either `Loco Translate` plugin or `Poedit` Software. Please generate PO and MO files in the default wordpress languages/plugins directory. After adding translations for the strings, you need to generate the JSON files.

## Generating JSON

To generate JSON from your translations, save your translation file in default wordpress languages directory then run the following command in the same directory: Note `xx_YY` refers to the locale (eg. `en_US`)

```
wp i18n make-json devdiggers-multipos-for-fluentcart-xx_YY.po --no-purge
```