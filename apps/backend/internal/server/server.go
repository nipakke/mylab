package server

import (
	"net/http"
	"time"

	"github.com/example/dashboard/apps/backend/internal/handler"
	"github.com/example/dashboard/apps/backend/internal/middleware"
)

// Config holds everything needed to run the HTTP server.
type Config struct {
	Port         string
	ReadTimeout  time.Duration
	WriteTimeout time.Duration
	IdleTimeout  time.Duration
}

// New builds a configured http.Server with routes attached.
func New(cfg Config) *http.Server {
	mux := http.NewServeMux()

	h := handler.New()
	mux.HandleFunc("GET /health", h.Health)
	mux.HandleFunc("GET /{$}", h.Index)

	return &http.Server{
		Addr:         ":" + cfg.Port,
		Handler:      middleware.Chain(mux, middleware.Logger, middleware.Recovery),
		ReadTimeout:  cfg.ReadTimeout,
		WriteTimeout: cfg.WriteTimeout,
		IdleTimeout:  cfg.IdleTimeout,
	}
}
