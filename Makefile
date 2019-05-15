
.PHONEY: test
test: test-unit test-api

.PHONEY: test-unit
test-unit:
	./vendor/bin/phpunit --coverage-php=coverage/unit.cov

.PHONEY: test-api
test-api:
	env REPORT_COVERAGE=true dredd

.PHONEY: coverage-clover
coverage-clover:
	./vendor/bin/phpcov merge --clover=clover.xml coverage/

.PHONEY: coverage-html
coverage-html:
	./vendor/bin/phpcov merge --html=coverage/html coverage/

.PHONEY: coverage-text
coverage-text:
	./vendor/bin/phpcov merge --text coverage/

.PHONEY: clean-coverage
clean-coverage:
	rm -rf coverage/* clover.xml

.PHONEY: docs
docs: docs/Assessor\ API.html

docs/Assessor\ API.html: docs/Assessor\ API.apib
	docker run -ti --rm -v $(PWD):/docs humangeo/aglio --theme-template triple -i docs/Assessor\ API.apib -o docs/Assessor\ API.html

.PHONEY: clean
clean: clean-coverage
	rm -rf docs/Assessor\ API.html
