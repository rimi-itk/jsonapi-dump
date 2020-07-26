# jsonapi-get

Fetch JSON:API data and save to local filesystem.

Fetches data from a [JSON:API](https://jsonapi.org/) endpoint and saves the
result as a local file. All [`links`
endpoints](https://jsonapi.org/format/#document-resource-object-links) are
fetched and saved as well.

Optionally, file assets, e.g. images, can also be fetched and stored locally.

All urls in the saved files (data from `links` and any downloaded assets)
will reference local files. See below for an example.

## Installation

```sh
composer install
```

## Usage

```sh
./jsonapi-get --help
```

### Example

This example

* Downloads data from `https://admin.os2conticki.srvitkhulk.itkdev.dk/api/conference/ed490668-3cd1-483a-8035-f4438646dce2`
* Saves result in the `data` folder
* Writes the main JSON:API document in the file `conference.json` (in the `data`
  folder)
* Downloads files from urls matching `/images|files/`

```sh
./jsonapi-get \
  --output-directory=data \
  --output-filename=conference.json \
  --file-url-pattern='/images|files/' \
  https://admin.os2conticki.srvitkhulk.itkdev.dk/api/conference/ed490668-3cd1-483a-8035-f4438646dce2
```

The result can be served by running

```sh
php -S 127.0.0.1:8888 -t .
```

and loading
[`http://127.0.0.1:8888/data/conference.json`](http://127.0.0.1:8888/data/conference.json).
