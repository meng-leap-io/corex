resource "kubernetes_namespace" "monitoring" {
  metadata {
    name = var.namespace
    labels = {
      name        = var.namespace
      environment = var.environment
    }
  }
}

resource "helm_release" "prometheus" {
  name       = "prometheus"
  namespace  = kubernetes_namespace.monitoring.metadata[0].name
  repository = "https://prometheus-community.github.io/helm-charts"
  chart      = "kube-prometheus-stack"
  version    = "60.0.0"

  values = [
    templatefile("${path.module}/values/prometheus.yaml", {
      environment = var.environment
      cluster_name = var.cluster_name
    })
  ]

  set {
    name  = "grafana.adminPassword"
    value = var.grafana_admin_password
  }

  set {
    name  = "prometheus.prometheusSpec.scrapeInterval"
    value = "30s"
  }

  set {
    name  = "prometheus.prometheusSpec.evaluationInterval"
    value = "30s"
  }

  set {
    name  = "prometheus.prometheusSpec.retention"
    value = "15d"
  }

  set {
    name  = "prometheus.prometheusSpec.resources.requests.memory"
    value = "2Gi"
  }

  set {
    name  = "prometheus.prometheusSpec.resources.limits.memory"
    value = "4Gi"
  }

  depends_on = [kubernetes_namespace.monitoring]
}

resource "helm_release" "loki" {
  count      = var.enable_loki ? 1 : 0
  name       = "loki"
  namespace  = kubernetes_namespace.monitoring.metadata[0].name
  repository = "https://grafana.github.io/helm-charts"
  chart      = "loki"
  version    = "6.0.0"

  values = [templatefile("${path.module}/values/loki.yaml", {
    environment = var.environment
  })]

  depends_on = [kubernetes_namespace.monitoring]
}

resource "helm_release" "promtail" {
  name       = "promtail"
  namespace  = kubernetes_namespace.monitoring.metadata[0].name
  repository = "https://grafana.github.io/helm-charts"
  chart      = "promtail"
  version    = "6.16.0"

  values = [templatefile("${path.module}/values/promtail.yaml", {
    environment = var.environment
    loki_service = var.enable_loki ? "loki-gateway.${kubernetes_namespace.monitoring.metadata[0].name}.svc.cluster.local" : ""
  })]

  depends_on = [kubernetes_namespace.monitoring]
}

resource "kubernetes_manifest" "grafana_dashboards" {
  for_each = {
    corex-api = templatefile("${path.module}/dashboards/api.json", {
      environment = var.environment
    })
    corex-php = templatefile("${path.module}/dashboards/php.json", {
      environment = var.environment
    })
    corex-agent = templatefile("${path.module}/dashboards/agent.json", {
      environment = var.environment
    })
  }

  manifest = {
    apiVersion = "v1"
    kind       = "ConfigMap"
    metadata = {
      name      = "grafana-dashboard-${each.key}"
      namespace = kubernetes_namespace.monitoring.metadata[0].name
      labels = {
        grafana_dashboard = "1"
      }
    }
    data = {
      "${each.key}.json" = each.value
    }
  }

  depends_on = [helm_release.prometheus]
}

resource "kubernetes_manifest" "pod_monitors" {
  for_each = {
    ai-gateway = {
      namespace  = "corex"
      port       = "8000"
      path       = "/metrics"
      interval   = "15s"
    }
    backend = {
      namespace  = "corex"
      port       = "9000"
      path       = "/metrics"
      interval   = "30s"
    }
  }

  manifest = {
    apiVersion = "monitoring.coreos.com/v1"
    kind       = "PodMonitor"
    metadata = {
      name      = "corex-${each.key}"
      namespace = kubernetes_namespace.monitoring.metadata[0].name
      labels = {
        release = "prometheus"
      }
    }
    spec = {
      selector = {
        matchLabels = {
          "app.kubernetes.io/part-of" = "corex-platform"
        }
      }
      namespaceSelector = {
        matchNames = [each.value.namespace]
      }
      podMetricsEndpoints = [{
        port     = each.value.port
        path     = each.value.path
        interval = each.value.interval
      }]
    }
  }

  depends_on = [helm_release.prometheus]
}

resource "kubernetes_manifest" "prometheus_rules" {
  manifest = {
    apiVersion = "monitoring.coreos.com/v1"
    kind       = "PrometheusRule"
    metadata = {
      name      = "corex-alerts"
      namespace = kubernetes_namespace.monitoring.metadata[0].name
      labels = {
        release = "prometheus"
      }
    }
    spec = {
      groups = [{
        name = "corex.rules"
        rules = [
          {
            alert = "HighRequestLatency"
            expr  = "histogram_quantile(0.95, sum(rate(http_request_duration_seconds_bucket[5m])) by (le, service)) > 2"
            for   = "5m"
            annotations = {
              summary     = "High request latency on {{ $labels.service }}"
              description = "{{ $labels.service }} has 95th percentile latency above 2s for 5 minutes"
            }
            labels = { severity = "warning" }
          },
          {
            alert = "HighErrorRate"
            expr  = "sum(rate(http_requests_total{status=~\"5..\"}[5m])) / sum(rate(http_requests_total[5m])) > 0.05"
            for   = "5m"
            annotations = {
              summary     = "High error rate"
              description = "Error rate is above 5% for 5 minutes"
            }
            labels = { severity = "critical" }
          },
          {
            alert = "HighTokenUsage"
            expr  = "rate(tokens_total[5m]) > 100000"
            for   = "5m"
            annotations = {
              summary     = "High AI token consumption"
              description = "Token usage is above 100,000 tokens per second"
            }
            labels = { severity = "warning" }
          },
          {
            alert = "AIGatewayDown"
            expr  = "up{job=~\"corex-ai-gateway\"} == 0"
            for   = "1m"
            annotations = {
              summary     = "AI Gateway is down"
              description = "AI Gateway pod is not reachable"
            }
            labels = { severity = "critical" }
          },
          {
            alert = "HighCPUUsage"
            expr  = "sum(rate(container_cpu_usage_seconds_total{namespace=\"corex\"}[5m])) by (pod) > 0.8"
            for   = "10m"
            annotations = {
              summary     = "High CPU usage on {{ $labels.pod }}"
              description = "CPU usage is above 80% for 10 minutes"
            }
            labels = { severity = "warning" }
          },
          {
            alert = "RedisDown"
            expr  = "redis_up == 0"
            for   = "1m"
            annotations = {
              summary     = "Redis is down"
              description = "Redis instance is not reachable"
            }
            labels = { severity = "critical" }
          },
        ]
      }]
    }
  }
  depends_on = [helm_release.prometheus]
}

resource "kubernetes_manifest" "grafana_service_monitor" {
  manifest = {
    apiVersion = "monitoring.coreos.com/v1"
    kind       = "ServiceMonitor"
    metadata = {
      name      = "corex-services"
      namespace = kubernetes_namespace.monitoring.metadata[0].name
      labels = {
        release = "prometheus"
      }
    }
    spec = {
      selector = {
        matchLabels = {
          "app.kubernetes.io/part-of" = "corex-platform"
        }
      }
      namespaceSelector = {
        matchNames = ["corex"]
      }
      endpoints = [
        {
          port     = "http"
          path     = "/metrics"
          interval = "15s"
        }
      ]
    }
  }
  depends_on = [helm_release.prometheus]
}
