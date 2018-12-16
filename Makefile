# Build the PHP runtimes
runtimes: runtime-default runtime-fpm runtime-loop
runtime-default:
	cd runtime/default && sh publish.sh
runtime-fpm:
	cd runtime/fpm && sh publish.sh
runtime-loop:
	cd runtime/loop && sh publish.sh

.PHONY: runtimes runtime-default runtime-loop
