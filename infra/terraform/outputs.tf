output "vpc_id" {
  description = "VPC ID"
  value       = module.vpc.vpc_id
}

output "vpc_private_subnets" {
  description = "Private subnet IDs"
  value       = module.vpc.private_subnets
}

output "vpc_public_subnets" {
  description = "Public subnet IDs"
  value       = module.vpc.public_subnets
}

output "rds_endpoint" {
  description = "RDS endpoint address"
  value       = module.rds.endpoint
  sensitive   = true
}

output "rds_reader_endpoint" {
  description = "RDS reader endpoint"
  value       = module.rds.reader_endpoint
}

output "redis_endpoint" {
  description = "ElastiCache Redis endpoint"
  value       = module.redis.endpoint
  sensitive   = true
}

output "container_registry_url" {
  description = "ECR repository URLs"
  value = {
    backend    = module.container_registry.backend_url
    ai_gateway = module.container_registry.ai_gateway_url
    nginx      = module.container_registry.nginx_url
  }
}

output "eks_cluster_name" {
  description = "EKS cluster name"
  value       = module.eks.cluster_name
}

output "eks_cluster_endpoint" {
  description = "EKS cluster endpoint"
  value       = module.eks.cluster_endpoint
}

output "monitoring_prometheus_url" {
  description = "Prometheus server URL"
  value       = module.monitoring.prometheus_url
}

output "monitoring_grafana_url" {
  description = "Grafana dashboard URL"
  value       = module.monitoring.grafana_url
}
