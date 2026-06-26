variable "cluster_name" {
  description = "EKS cluster name"
  type        = string
}

variable "namespace" {
  description = "Kubernetes namespace for monitoring"
  type        = string
  default     = "monitoring"
}

variable "environment" {
  description = "Environment name"
  type        = string
  default     = "production"
}

variable "grafana_admin_password" {
  description = "Grafana admin password"
  type        = string
  sensitive   = true
  default     = "admin"
}

variable "enable_loki" {
  description = "Enable Loki log aggregation"
  type        = bool
  default     = true
}

variable "tags" {
  description = "Common tags"
  type        = map(string)
  default     = {}
}
