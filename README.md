# Mock Server for Generic Extractor
This application is a simple mock server on which various APIs can be simulated and on which
various configurations of Keboola [Generic Extractor](todo) can be tested.

## Running Generic Extractor Examples
To run sample APIs for Generic Extractor, you don't need this repository directly. It will be used 
automatically when you [run the examples](http://developers.keboola.com/extend/generic-extractor/running/#running-examples).
Also the actual API simulations and Generic Extractor configurations are contained in the 
[Generic Extractor repository](https://github.com/keboola/generic-extractor/).

## Running the Server
Run the server using [Docker](https://www.docker.com/). 

```
git clone https://github.com/keboola/ex-generic-mock-server.git
cd ex-generic-mock-server
docker-compose up
```

The server will be available at `localhost:8888`. When you request `http://localhost:8888/sample`, you should get:

```
{
	"sample": "successfull"
}
```

## Creating Examples
If you start the server via `docker-compose` as described above, the `data` folder will be mapped into the container. All API simulations
are created using simple text files resembling HTTP protocol communication. To create a new API simulation:

- create an arbitrarily named directory (e.g. `my-configuration`)
- create an arbitrarily named `.request` and `.response` files (e.g. `search.request` and `search.response`)
- write a HTTP request into the `.request` file (e.g. `GET /my-configuration/search`). The request URL is compeletely arbitrary, but 
to maintain sanity, it is best to name it after the directory and file name.
- write a JSON response into the `.response` file (e.g. `{"numberOfResults": 0}`) 
- send the apropriate HTTP request to `localhost:8888` and you should see the response.

### Rules and Limitations
Each `.request` file must be paired with a `.response` file. The `.response` file is completely arbitrary. The `.request` file must 
contain the HTTP method on the first line, followed by a space and the URL (including URL parameters). For a POST request, make an 
empty line after that and put the request body. Line endings must be `\r\n` (CRLF). E.g.

```
GET /foo/bar
```

or 

```
POST /foo/bar?baz=bar&foo=bar

{"whatever": "needs-to-be"}
```

Matching of the requests is done exactly and stupidly. That is `POST /foo/bar?baz=bar&foo=bar` matches only POST to `/foo/bar?baz=bar&foo=bar`. 
It won't match on `/foo/bar/?baz=bar&foo=bar` or `/foo/bar?foo=bar&baz=bar`. You need to create separate `.request` and `.response` files 
if you need this. Also the URL must be urlencoded, therefore use `foo%5B0%5D=bar&foo%5B1%5D=baz` instead of `foo[0]=bar&foo[1]=baz`.

#### Headers
You can create a `.requestHeaders` file. The file contains HTTP headers, each on a single line (line delimiter is again CRLF), for example:

```
Accept: application/json
```

If a `.requestHeaders` file is created, the HTTP request sent to the mock server **must contain** all specified headers (in any order) to
match for the response. It may send other headers which will be ignored. This means you may create multiple `.request` files with same URL,
provided that they are differentiated by `.requestHeaders` file. 

You may also create a `.responseHeaders` file which can contain the headers which will be sent with the response. If the file is not present
a `Content-type: application/json` will be sent automatically. If the `.responseHeaders` file is present, that header will not be sent.

### Full example
Create a directory `my-api`. Create a file in that directory `search.request` with content:

```
GET /my-api/search?foo=bar
```

Crate a file `search.response` with content:

```
{"search": "yes!"}
```

Create a file `search.responseHeaders` with content:

```
Accept: application/json
```

Create a file `search2.request` with content:

```
GET /my-api/search?foo=bar
```

Create a file `search2.response` with content:

```
{"search": "no"}
```

Now you can run HTTP requests against `localhost:8888`. If you send a GET request to `/my-api/search?foo=bar` with the 
`Accept: application/json` header, you will obtain the reponse `{"search": "yes!"}`. If you send a GET request to 
`/my-api/search?foo=bar` without that header, you will obtain the response `{"search": "no"}`. All other request to 
`localhost:8888` will result in an error.
