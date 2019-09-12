#!/bin/bash

export APP_ENV=testing
export DB_CONNECTION=sqlite
export DB_DATABASE=/tmp/assessor-dredd.sqlite
export CACHE_DRIVER=file
export KEEPER_BASE_URI=http://localhost:8081/
export FRONTEND_TOKEN=MyFrontendToken

if [[ ! -d keeper ]]; then
    echo "Please clone https://github.com/appocular/keeper.git and run composer install in it."
fi

function cleanup {
        local pids=$(jobs -pr)
        [ -n "$pids" ] && kill $pids
        rm -f /tmp/assessor-dredd.sqlite
}

trap cleanup INT TERM ERR
trap cleanup EXIT

./artisan migrate:fresh
./artisan assessor:add-repo test MyRepoToken

php -S 0.0.0.0:8081 -t keeper/public &
php -S 0.0.0.0:8080 -t public &

wait
