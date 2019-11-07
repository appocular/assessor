
.PHONEY: phpcs
phpcs:
	./vendor/bin/phpcs

.PHONEY: test
test: test-unit phpcs test-api

.PHONEY: test-unit
test-unit:
	phpdbg -qrr ./vendor/bin/phpunit --coverage-php=coverage/unit.cov

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

.PHONEY: watch-test
watch-test:
	while true; do \
	  find . \( -name .git -o -name vendor \) -prune -o -name '#*' -o -name '*.php' -a -print | entr -cd make test-unit test-api; \
	done
