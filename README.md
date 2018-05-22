Webmon
======

## License
Webmon - program to monitor web pages for change and detect the change
Copyright (C) 2013 Prabhas Gupte

This file is part of Webmon.

Webmon is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

Webmon is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with Webmon.  If not, see <http://www.gnu.org/licenses/gpl.txt>

## About Webmon
Webmon is a PHP script to monitor web pages.
It have following objectives:
1) Detect whether listed webpage have any change in contents.
2) If change is detected, calculate the difference, and output what have changed. 
3) Detect both - positive and negative changes.

## Supported Tags
`webmon:latest`

## Running the container
You can use following command to run the container:

`docker run -v /path/to/your/seeds.txt:/app/store/seeds.txt -v /path/to/your/data.json:/app/store/data.json webmon:latest`

Where, 
`/path/to/your/seeds.txt` is the input file, containing an URL per line.
`/path/to/your/data.json` is the output file, containing the data produced by webmon. 

### Creating seeds file
This file is the input to webmon, containing an URL per line. These are the URLs you wish to monitor for contents change. For example:

```
http://example.com
http://example.net
```

If you want to temporarily skip any URL, you can put a hash (`#`) at the beginning of the line. For example:

```
http://example.com
# http://example.net
```


