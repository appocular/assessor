#!/bin/bash

export APP_ENV=local
export DB_CONNECTION=sqlite
export DB_DATABASE=/tmp/assessor-dredd.sqlite
export CACHE_DRIVER=file
export KEEPER_BASE_URI=http://localhost:8081/

if [[ ! -d keeper ]]; then
    git clone https://github.com/appocular/keeper.git keeper
fi

function cleanup {
        local pids=$(jobs -pr)
        [ -n "$pids" ] && kill $pids
}

trap "exit" INT TERM ERR
trap cleanup EXIT

./artisan migrate:fresh

php -S 0.0.0.0:8081 -t keeper/public &
php -S 0.0.0.0:8080 -t public &

wait
