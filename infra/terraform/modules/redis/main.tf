resource "aws_elasticache_subnet_group" "this" {
  name        = "${var.cluster_id}-subnet-group"
  description = "ElastiCache subnet group for ${var.cluster_id}"
  subnet_ids  = var.subnet_ids
  tags        = var.tags
}

resource "aws_security_group" "redis" {
  name        = "${var.cluster_id}-redis-sg"
  description = "Security group for ElastiCache Redis"
  vpc_id      = var.vpc_id

  ingress {
    from_port       = 6379
    to_port         = 6379
    protocol        = "tcp"
    security_groups = [var.eks_security_group_id]
    description     = "EKS worker nodes"
  }

  ingress {
    from_port   = 6379
    to_port     = 6379
    protocol    = "tcp"
    cidr_blocks = [var.vpc_cidr]
    description = "VPC internal"
  }

  egress {
    from_port   = 0
    to_port     = 0
    protocol    = "-1"
    cidr_blocks = ["0.0.0.0/0"]
  }

  tags = merge(var.tags, {
    Name = "${var.cluster_id}-redis-sg"
  })
}

resource "aws_elasticache_parameter_group" "this" {
  name        = "${var.cluster_id}-param-group"
  family      = "redis7"
  description = "Custom parameter group for ${var.cluster_id}"

  parameter {
    name  = "timeout"
    value = "300"
  }
  parameter {
    name  = "maxmemory-policy"
    value = "allkeys-lru"
  }
  parameter {
    name  = "notify-keyspace-events"
    value = "Ex"
  }

  tags = var.tags
}

resource "aws_elasticache_cluster" "this" {
  cluster_id           = var.cluster_id
  engine               = "redis"
  engine_version       = "7.1"
  node_type            = var.node_type
  num_cache_nodes      = var.num_cache_nodes
  parameter_group_name = aws_elasticache_parameter_group.this.name
  subnet_group_name    = aws_elasticache_subnet_group.this.name
  security_group_ids   = [aws_security_group.redis.id]

  port                 = 6379
  apply_immediately    = false
  auto_minor_version_upgrade = true

  at_rest_encryption_enabled = true
  transit_encryption_enabled = true

  maintenance_window = "sun:06:00-sun:07:00"
  snapshot_retention_limit = 7
  snapshot_window          = "04:00-05:00"

  tags = var.tags
}

resource "aws_ssm_parameter" "redis_endpoint" {
  name        = "/${var.cluster_id}/redis-endpoint"
  description = "ElastiCache Redis primary endpoint"
  type        = "SecureString"
  value       = aws_elasticache_cluster.this.cache_nodes[0].address
  tags        = var.tags
}
