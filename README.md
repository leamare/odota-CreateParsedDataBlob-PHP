# CreateParsedDataBlob

Custom re-implementation of CreateParsedDataBlob script/function from odota/core

Key differences
- it's using PHP7 instead of JS, mostly because it was intended to use as part of LRG-based stuff
- it's pulling matchinfo data from epilogue (which is ignored by OpenDota)