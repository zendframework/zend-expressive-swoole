#!/bin/bash
pecl install swoole-2.2.0 << EOF
`#enable debug/trace log support? [no] :`
`#enable sockets support? [no] :`y
`#enable openssl support? [no] :`
`#enable http2 support? [no] :`
`#enable async-redis support? [no] :`
`#enable mysqlnd support? [no] :`
`#enable postgresql coroutine client support support? [no] :`
EOF
