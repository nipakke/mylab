.PHONY: run build test lint clean

APP_NAME := dashboard-server
BIN_DIR  := bin

run:
	go run ./backend/cmd/server

build:
	go build -o $(BIN_DIR)/$(APP_NAME) ./backend/cmd/server

test:
	go test ./... -count=1 -v

lint:
	go vet ./...

clean:
	rm -rf $(BIN_DIR)
