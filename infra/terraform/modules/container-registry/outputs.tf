output "backend_url" {
  value = aws_ecr_repository.backend.repository_url
}

output "ai_gateway_url" {
  value = aws_ecr_repository.ai_gateway.repository_url
}

output "nginx_url" {
  value = aws_ecr_repository.nginx.repository_url
}

output "backend_arn" {
  value = aws_ecr_repository.backend.arn
}

output "ai_gateway_arn" {
  value = aws_ecr_repository.ai_gateway.arn
}

output "nginx_arn" {
  value = aws_ecr_repository.nginx.arn
}
