resource "aws_ecr_repository" "backend" {
  name                 = "${var.project_name}/backend"
  image_tag_mutability = "IMMUTABLE"
  force_delete         = false

  image_scanning_configuration {
    scan_on_push = true
  }

  encryption_configuration {
    encryption_type = "AES256"
  }

  tags = var.tags
}

resource "aws_ecr_repository" "ai_gateway" {
  name                 = "${var.project_name}/ai-gateway"
  image_tag_mutability = "IMMUTABLE"
  force_delete         = false

  image_scanning_configuration {
    scan_on_push = true
  }

  encryption_configuration {
    encryption_type = "AES256"
  }

  tags = var.tags
}

resource "aws_ecr_repository" "nginx" {
  name                 = "${var.project_name}/nginx"
  image_tag_mutability = "IMMUTABLE"
  force_delete         = false

  image_scanning_configuration {
    scan_on_push = true
  }

  encryption_configuration {
    encryption_type = "AES256"
  }

  tags = var.tags
}

resource "aws_ecr_lifecycle_policy" "backend" {
  repository = aws_ecr_repository.backend.name
  policy = jsonencode({
    rules = [{
      rulePriority = 1
      description  = "Keep last 10 production images"
      selection = {
        tagStatus     = "tagged"
        tagPrefixList = ["prod-"]
        countType     = "imageCountMoreThan"
        countNumber   = 10
      }
      action = { type = "expire" }
      }, {
      rulePriority = 2
      description  = "Remove untagged images after 7 days"
      selection = {
        tagStatus   = "untagged"
        countType   = "sinceImagePushed"
        countNumber = 7
        countUnit   = "days"
      }
      action = { type = "expire" }
    }]
  })
}

resource "aws_ecr_lifecycle_policy" "ai_gateway" {
  repository = aws_ecr_repository.ai_gateway.name
  policy = jsonencode({
    rules = [{
      rulePriority = 1
      description  = "Keep last 10 production images"
      selection = {
        tagStatus     = "tagged"
        tagPrefixList = ["prod-"]
        countType     = "imageCountMoreThan"
        countNumber   = 10
      }
      action = { type = "expire" }
      }, {
      rulePriority = 2
      description  = "Remove untagged images after 7 days"
      selection = {
        tagStatus   = "untagged"
        countType   = "sinceImagePushed"
        countNumber = 7
        countUnit   = "days"
      }
      action = { type = "expire" }
    }]
  })
}

resource "aws_ecr_lifecycle_policy" "nginx" {
  repository = aws_ecr_repository.nginx.name
  policy = jsonencode({
    rules = [{
      rulePriority = 1
      description  = "Keep last 5 production images"
      selection = {
        tagStatus     = "tagged"
        tagPrefixList = ["prod-"]
        countType     = "imageCountMoreThan"
        countNumber   = 5
      }
      action = { type = "expire" }
      }, {
      rulePriority = 2
      description  = "Remove untagged images after 7 days"
      selection = {
        tagStatus   = "untagged"
        countType   = "sinceImagePushed"
        countNumber = 7
        countUnit   = "days"
      }
      action = { type = "expire" }
    }]
  })
}

resource "aws_ecr_repository_policy" "backend" {
  repository = aws_ecr_repository.backend.name
  policy = jsonencode({
    Version = "2012-10-17"
    Statement = [{
      Sid    = "EKS pull access"
      Effect = "Allow"
      Principal = {
        AWS = var.eks_node_role_arn
      }
      Action = [
        "ecr:BatchGetImage",
        "ecr:GetDownloadUrlForLayer",
        "ecr:GetAuthorizationToken",
      ]
    }]
  })
}

resource "aws_ecr_repository_policy" "ai_gateway" {
  repository = aws_ecr_repository.ai_gateway.name
  policy = jsonencode({
    Version = "2012-10-17"
    Statement = [{
      Sid    = "EKS pull access"
      Effect = "Allow"
      Principal = {
        AWS = var.eks_node_role_arn
      }
      Action = [
        "ecr:BatchGetImage",
        "ecr:GetDownloadUrlForLayer",
        "ecr:GetAuthorizationToken",
      ]
    }]
  })
}

resource "aws_ecr_repository_policy" "nginx" {
  repository = aws_ecr_repository.nginx.name
  policy = jsonencode({
    Version = "2012-10-17"
    Statement = [{
      Sid    = "EKS pull access"
      Effect = "Allow"
      Principal = {
        AWS = var.eks_node_role_arn
      }
      Action = [
        "ecr:BatchGetImage",
        "ecr:GetDownloadUrlForLayer",
        "ecr:GetAuthorizationToken",
      ]
    }]
  })
}
