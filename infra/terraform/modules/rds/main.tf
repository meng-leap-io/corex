resource "aws_db_subnet_group" "this" {
  name        = "${var.identifier}-subnet-group"
  description = "Database subnet group for ${var.identifier}"
  subnet_ids  = var.subnet_ids
  tags        = var.tags
}

resource "aws_security_group" "rds" {
  name        = "${var.identifier}-rds-sg"
  description = "Security group for RDS instance"
  vpc_id      = var.vpc_id

  ingress {
    from_port       = 5432
    to_port         = 5432
    protocol        = "tcp"
    security_groups = [var.eks_security_group_id]
    description     = "EKS worker nodes"
  }

  ingress {
    from_port   = 5432
    to_port     = 5432
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
    Name = "${var.identifier}-rds-sg"
  })
}

resource "aws_db_parameter_group" "this" {
  name        = "${var.identifier}-pg"
  family      = "postgres16"
  description = "Custom parameter group for ${var.identifier}"

  parameter {
    name  = "shared_buffers"
    value = "{DBInstanceClassMemory/32768}"
  }
  parameter {
    name  = "effective_cache_size"
    value = "{DBInstanceClassMemory/16384}"
  }
  parameter {
    name  = "maintenance_work_mem"
    value = "{DBInstanceClassMemory/65536}"
  }
  parameter {
    name  = "random_page_cost"
    value = "1.1"
  }
  parameter {
    name  = "log_min_duration_statement"
    value = "1000"
  }
  parameter {
    name         = "rds.force_ssl"
    value        = "1"
    apply_method = "pending-reboot"
  }

  tags = var.tags
}

resource "aws_db_instance" "primary" {
  identifier = var.identifier

  engine         = "postgres"
  engine_version = var.engine_version
  instance_class = var.instance_class

  db_name  = var.db_name
  username = var.username
  password = var.password

  allocated_storage     = var.allocated_storage
  max_allocated_storage = var.max_allocated_storage
  storage_type          = "gp3"
  storage_encrypted     = true

  db_subnet_group_name   = aws_db_subnet_group.this.name
  vpc_security_group_ids = [aws_security_group.rds.id]
  parameter_group_name   = aws_db_parameter_group.this.name

  backup_retention_period = 30
  backup_window           = "03:00-04:00"
  maintenance_window      = "sun:05:00-sun:06:00"
  copy_tags_to_snapshot   = true
  skip_final_snapshot     = false
  final_snapshot_identifier = "${var.identifier}-final-${formatdate("YYYY-MM-DD-hhmm", timestamp())}"
  deletion_protection     = true

  performance_insights_enabled          = true
  performance_insights_retention_period = 7

  enabled_cloudwatch_logs_exports = ["postgresql", "upgrade"]

  auto_minor_version_upgrade = true
  multi_az                   = var.multi_az

  tags = var.tags
}

resource "aws_db_instance" "replica" {
  count = var.multi_az ? 0 : var.read_replicas

  identifier = "${var.identifier}-replica-${count.index + 1}"
  replicate_source_db = aws_db_instance.primary.identifier

  instance_class = var.instance_class
  storage_encrypted = true

  copy_tags_to_snapshot = true
  deletion_protection   = true
  skip_final_snapshot   = true

  performance_insights_enabled          = true
  performance_insights_retention_period = 7

  tags = merge(var.tags, {
    Name = "${var.identifier}-replica-${count.index + 1}"
  })
}

resource "aws_ssm_parameter" "db_password" {
  name        = "/${var.identifier}/db-password"
  description = "RDS database master password"
  type        = "SecureString"
  value       = var.password
  tags        = var.tags
}
