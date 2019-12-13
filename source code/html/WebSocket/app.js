var WebSocketServer = require('websocket').server;
var http = require('http');

var server = http.createServer(function (req, res) {
  console.log('Received request for ' + req.url);
  res.writeHead(404);
  res.end();
});

server.listen(5250, function () {
  console.log('Server is listening on port 5250');
});

wsServer = new WebSocketServer({
  httpServer: server,
  autoAcceptConnections: false
});

wsServer.on('request', function (request) {
  var connection = request.accept('example-echo', request.origin);
  connection.on('message', function (message) {
    if (message.type === 'utf8') {
      console.log('Received message: ' + message.utf8Data);
      connection.sendUTF(message.utf8Data);
    }
    else if (message.type === 'binary') {
      connection.sendBytes(message.binaryData);
    }

    connection.on('close', function (reasonCode, description) {
      console.log('Peer ' + connection.remoteAddress + ' disconnected.');
    });
  });
});
