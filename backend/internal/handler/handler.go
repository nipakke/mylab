package handler

import (
	"encoding/json"
	"net/http"
	"runtime"
)

// Handler groups HTTP handler methods.
type Handler struct{}

// New returns a Handler with its dependencies wired up.
func New() *Handler { return &Handler{} }

// Health responds with a basic readiness payload.
func (h *Handler) Health(w http.ResponseWriter, r *http.Request) {
	writeJSON(w, http.StatusOK, map[string]any{
		"status": "ok",
	})
}

// Index is the root endpoint; useful as a smoke test.
func (h *Handler) Index(w http.ResponseWriter, r *http.Request) {
	writeJSON(w, http.StatusOK, map[string]any{
		"service": "dashboard",
		"go":      runtime.Version(),
	})
}

func writeJSON(w http.ResponseWriter, status int, v any) {
	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(status)
	json.NewEncoder(w).Encode(v)
}
