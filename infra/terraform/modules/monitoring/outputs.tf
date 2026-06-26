output "prometheus_url" {
  value = "https://prometheus.${var.environment}.corex.dev"
}

output "grafana_url" {
  value = "https://grafana.${var.environment}.corex.dev"
}

output "alertmanager_url" {
  value = "https://alertmanager.${var.environment}.corex.dev"
}

output "namespace" {
  value = kubernetes_namespace.monitoring.metadata[0].name
}
