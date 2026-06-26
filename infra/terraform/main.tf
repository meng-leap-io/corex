terraform {
  required_version = ">= 1.6"
  required_providers {
    aws = {
      source  = "hashicorp/aws"
      version = "~> 5.0"
    }
    kubernetes = {
      source  = "hashicorp/kubernetes"
      version = "~> 2.0"
    }
    helm = {
      source  = "hashicorp/helm"
      version = "~> 2.0"
    }
  }
  backend "s3" {
    bucket         = "corex-terraform-state"
    key            = "production/terraform.tfstate"
    region         = "us-east-1"
    encrypt        = true
    dynamodb_table = "corex-terraform-locks"
  }
}

provider "aws" {
  region = var.region
  default_tags {
    tags = var.tags
  }
}

provider "kubernetes" {
  host                   = module.eks.cluster_endpoint
  cluster_ca_certificate = base64decode(module.eks.cluster_certificate_authority)
  exec {
    api_version = "client.authentication.k8s.io/v1beta1"
    command     = "aws"
    args        = ["eks", "get-token", "--cluster-name", module.eks.cluster_name]
  }
}

provider "helm" {
  kubernetes {
    host                   = module.eks.cluster_endpoint
    cluster_ca_certificate = base64decode(module.eks.cluster_certificate_authority)
    exec {
      api_version = "client.authentication.k8s.io/v1beta1"
      command     = "aws"
      args        = ["eks", "get-token", "--cluster-name", module.eks.cluster_name]
    }
  }
}

module "vpc" {
  source = "./modules/vpc"

  name                 = var.project_name
  environment          = var.environment
  vpc_cidr            = var.vpc_cidr
  availability_zones  = var.availability_zones
  private_subnet_cidrs = var.private_subnet_cidrs
  public_subnet_cidrs  = var.public_subnet_cidrs
  tags                 = var.tags
}

module "rds" {
  source = "./modules/rds"

  identifier        = "${var.project_name}-${var.environment}"
  engine_version    = "16.3"
  instance_class    = var.db_instance_class
  allocated_storage = var.db_allocated_storage
  db_name           = var.db_name
  username          = var.db_username
  password          = var.db_password
  subnet_ids        = module.vpc.private_subnets
  vpc_id            = module.vpc.vpc_id
  tags              = var.tags
}

module "redis" {
  source = "./modules/redis"

  cluster_id        = "${var.project_name}-${var.environment}"
  node_type         = var.redis_node_type
  num_cache_nodes   = var.redis_num_cache_nodes
  subnet_ids        = module.vpc.private_subnets
  vpc_id            = module.vpc.vpc_id
  tags              = var.tags
}

module "container_registry" {
  source = "./modules/container-registry"

  project_name = var.project_name
  environment  = var.environment
  tags         = var.tags
}

module "eks" {
  source = "terraform-aws-modules/eks/aws"
  version = "~> 20.0"

  cluster_name    = "${var.project_name}-${var.environment}"
  cluster_version = var.eks_cluster_version
  subnet_ids      = module.vpc.private_subnets
  vpc_id          = module.vpc.vpc_id

  cluster_endpoint_public_access = false
  cluster_endpoint_private_access = true

  eks_managed_node_groups = {
    corex = {
      desired_size = var.eks_desired_nodes
      min_size     = var.eks_min_nodes
      max_size     = var.eks_max_nodes
      instance_types = var.eks_node_instance_types
      capacity_type  = "ON_DEMAND"
      subnet_ids     = module.vpc.private_subnets
    }
  }

  tags = var.tags
}

resource "kubernetes_namespace" "corex" {
  metadata {
    name = "corex"
    labels = {
      environment = var.environment
    }
  }
  depends_on = [module.eks]
}

resource "kubernetes_namespace" "monitoring" {
  metadata {
    name = "corex-monitoring"
    labels = {
      environment = var.environment
    }
  }
  depends_on = [module.eks]
}

module "monitoring" {
  source = "./modules/monitoring"

  cluster_name       = module.eks.cluster_name
  namespace          = kubernetes_namespace.monitoring.metadata[0].name
  environment        = var.environment
  grafana_admin_password = var.grafana_admin_password
}

resource "kubernetes_secret" "corex-secrets" {
  metadata {
    name      = "corex-secrets"
    namespace = kubernetes_namespace.corex.metadata[0].name
  }
  data = {
    APP_KEY          = var.app_key
    DB_PASSWORD      = var.db_password
    REDIS_PASSWORD   = var.redis_password
    JWT_SECRET       = var.jwt_secret
    OPENAI_API_KEY   = var.openai_api_key
    ANTHROPIC_API_KEY = var.anthropic_api_key
    GROQ_API_KEY     = var.groq_api_key
    DEEPSEEK_API_KEY = var.deepseek_api_key
    SENTRY_DSN       = var.sentry_dsn
  }
  depends_on = [module.eks]
}
