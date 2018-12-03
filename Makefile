
test:
	./vendor/bin/phpunit
	phpdbg -qrr ./vendor/bin/phpspec run
	dredd
