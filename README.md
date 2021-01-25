# CreateParsedDataBlob

Custom re-implementation of CreateParsedDataBlob script/function from odota/core

Original version: https://github.com/odota/core/blob/master/processors/createParsedDataBlob.js

## How to use

- a) include `createParsedDataBlob.php` and call either `CreateParsedDataBlob\parseStream()` with a stream of line delimited JSON entities or `CreateParsedDataBlob\createParsedDataBlob()` with an array of parsed entities
- b) launch it via cli like `php create_datablob.cli.php < path_to_input_file > path_to_output` or via pipe

## Key differences

- it's using PHP7 instead of JS, mostly because it was intended to use as part of LRG-based stuff
- it's pulling matchinfo data from epilogue (which is ignored by OpenDota)
- Some deprecated stuff was removed, much less stuff in utils
- There were some object/array defferences in some places. Both are the same thing in PHP so I needed to do some tricky stuff for now

## Why

- Because.
- I wanted to use Clarity parser and produce output in a similar form to OpenDota JSON responses. Since most of my server side stuff is based on PHP and LRG, it would be easier and more natural to include PHP code. And since this part of odota/core does pretty much what I do, I just rewrote it one-to-one
- I wanted to add smokes data and inject epilogue data into response, but I doubt it will be included in main odota codebase anyway