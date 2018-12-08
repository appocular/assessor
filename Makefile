
.PHONEY: docs

test: test-spec test-unit test-api

test-spec:
	phpdbg -qrr ./vendor/bin/phpspec run


test-unit:
	./vendor/bin/phpunit

test-api:
	dredd

docs: docs.html

docs.html: docs/Assessor\ API.apib
	docker run -ti --rm -v $(PWD):/docs humangeo/aglio --theme-template triple -i docs/Assessor\ API.apib -o docs.html