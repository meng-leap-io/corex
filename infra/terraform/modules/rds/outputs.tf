output "endpoint" {
  value = aws_db_instance.primary.endpoint
}

output "reader_endpoint" {
  value = length(aws_db_instance.replica) > 0 ? aws_db_instance.replica[0].endpoint : aws_db_instance.primary.endpoint
}

output "port" {
  value = aws_db_instance.primary.port
}

output "arn" {
  value = aws_db_instance.primary.arn
}

output "security_group_id" {
  value = aws_security_group.rds.id
}
