
.PHONEY: docs

test: test-spec test-unit test-api

test-spec:
	phpdbg -qrr ./vendor/bin/phpspec run

test-unit:
	./vendor/bin/phpunit --coverage-php=coverage/unit.cov

test-api:
	env REPORT_COVERAGE=true dredd

coverage-clover:
	./vendor/bin/phpcov merge --clover=clover.xml coverage/

coverage-html:
	./vendor/bin/phpcov merge --html=coverage/html coverage/

coverage-text:
	./vendor/bin/phpcov merge --text coverage/

clean-coverage:
	rm -rf coverage/* clover.xml

docs: docs/Assessor\ API.html

docs/Assessor\ API.html: docs/Assessor\ API.apib
	docker run -ti --rm -v $(PWD):/docs humangeo/aglio --theme-template triple -i docs/Assessor\ API.apib -o docs/Assessor\ API.html

clean: clean-coverage
	rm -rf docs/Assessor\ API.html
