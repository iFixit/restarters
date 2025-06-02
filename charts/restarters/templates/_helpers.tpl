{{/*
Expand the name of the chart.
*/}}
{{- define "restarters.name" -}}
{{- default .Chart.Name .Values.nameOverride | trunc 63 | trimSuffix "-" }}
{{- end }}

{{/*
Create a default fully qualified app name.
We truncate at 63 chars because some Kubernetes name fields are limited to this (by the DNS naming spec).
If release name contains chart name it will be used as a full name.
*/}}
{{- define "restarters.fullname" -}}
{{- if .Values.fullnameOverride }}
{{- .Values.fullnameOverride | trunc 63 | trimSuffix "-" }}
{{- else }}
{{- $name := default .Chart.Name .Values.nameOverride }}
{{- if contains $name .Release.Name }}
{{- .Release.Name | trunc 63 | trimSuffix "-" }}
{{- else }}
{{- printf "%s-%s" .Release.Name $name | trunc 63 | trimSuffix "-" }}
{{- end }}
{{- end }}
{{- end }}

{{/*
Create chart name and version as used by the chart label.
*/}}
{{- define "restarters.chart" -}}
{{- printf "%s-%s" .Chart.Name .Chart.Version | replace "+" "_" | trunc 63 | trimSuffix "-" }}
{{- end }}

{{/*
Common labels
*/}}
{{- define "restarters.labels" -}}
helm.sh/chart: {{ include "restarters.chart" . }}
{{ include "restarters.selectorLabels" . }}
{{- if .Chart.AppVersion }}
app.kubernetes.io/version: {{ .Chart.AppVersion | quote }}
{{- end }}
app.kubernetes.io/managed-by: {{ .Release.Service }}
{{- end }}

{{/*
Selector labels
*/}}
{{- define "restarters.selectorLabels" -}}
app.kubernetes.io/name: {{ include "restarters.name" . }}
app.kubernetes.io/instance: {{ .Release.Name }}
{{- end }}

{{/*
Helper to generate environment variables from secrets
*/}}
{{- define "restarters.secretEnvVars" -}}
{{- if .Values.secrets.mapKeys.enabled }}
- name: MAPBOX_TOKEN
  valueFrom:
    secretKeyRef:
      name: {{ .Values.secrets.mapKeys.secretName }}
      key: {{ .Values.secrets.mapKeys.keys.mapboxToken }}
- name: GOOGLE_API_CONSOLE_KEY
  valueFrom:
    secretKeyRef:
      name: {{ .Values.secrets.mapKeys.secretName }}
      key: {{ .Values.secrets.mapKeys.keys.googleApiKey }}
{{- end }}
{{- if .Values.secrets.dbCredentials.enabled }}
- name: DB_HOST
  valueFrom:
    secretKeyRef:
      name: {{ .Values.secrets.dbCredentials.secretName }}
      key: {{ .Values.secrets.dbCredentials.keys.dbHost }}
- name: DB_PORT
  valueFrom:
    secretKeyRef:
      name: {{ .Values.secrets.dbCredentials.secretName }}
      key: {{ .Values.secrets.dbCredentials.keys.dbPort }}
- name: DB_DATABASE
  valueFrom:
    secretKeyRef:
      name: {{ .Values.secrets.dbCredentials.secretName }}
      key: {{ .Values.secrets.dbCredentials.keys.dbDatabase }}
- name: DB_USERNAME
  valueFrom:
    secretKeyRef:
      name: {{ .Values.secrets.dbCredentials.secretName }}
      key: {{ .Values.secrets.dbCredentials.keys.dbUsername }}
- name: DB_PASSWORD
  valueFrom:
    secretKeyRef:
      name: {{ .Values.secrets.dbCredentials.secretName }}
      key: {{ .Values.secrets.dbCredentials.keys.dbPassword }}
{{- end }}
{{- end }}

{{/*
Helper to generate setup environment variables
*/}}
{{- define "restarters.setupEnvVars" -}}
- name: MIGRATE
  value: "{{ .Values.setup.migrate | default "false" }}"
- name: SEEDING_TRUNCATE_SKILLS
  value: "{{ .Values.setup.seedingTruncateSkills | default "false" }}"
- name: SEED_SKILLS
  value: "{{ .Values.setup.seedSkills | default "false" }}"
- name: CREATE_ADMIN_USER
  value: "{{ .Values.setup.createAdminUser | default "false" }}"
- name: ADMIN_NAME
  value: "{{ .Values.setup.adminName | default "Restarters Admin" }}"
- name: ADMIN_EMAIL
  value: "{{ .Values.setup.adminEmail | default "admin@example.com" }}"
- name: ADMIN_PASSWORD
  value: "{{ .Values.setup.adminPassword | default "changeMeASAP" }}"
- name: ADMIN_ROLE
  value: "{{ .Values.setup.adminRole | default "2" }}"
{{- end }}

{{/*
Helper to generate database configuration based on mysql.enabled
*/}}
{{- define "restarters.dbConfig" -}}
{{- if .Values.mysql.enabled }}
DB_CONNECTION="mysql"
DB_HOST="{{ include "restarters.fullname" . }}-mysql"
DB_PORT="3306"
DB_DATABASE="{{ .Values.mysql.auth.database }}"
DB_USERNAME="{{ .Values.mysql.auth.username }}"
DB_PASSWORD="{{ .Values.mysql.auth.password }}"
{{- else if not .Values.secrets.dbCredentials.enabled }}
DB_CONNECTION="mysql"
DB_HOST="{{ .Values.envGroups.database.DB_HOST }}"
DB_PORT="{{ .Values.envGroups.database.DB_PORT }}"
DB_DATABASE="{{ .Values.envGroups.database.DB_DATABASE }}"
DB_USERNAME="{{ .Values.envGroups.database.DB_USERNAME }}"
DB_PASSWORD="{{ .Values.envGroups.database.DB_PASSWORD }}"
{{- else }}
# Fallback to env values
{{- include "restarters.envGroup" (dict "groupName" "database" "context" .) | nindent 8 }}
{{- end }}
{{- end }}

{{/*
Helper to generate environment variables for a specific group
*/}}
{{- define "restarters.envGroup" -}}
{{- $groupName := .groupName -}}
{{- $context := .context -}}
{{- if hasKey $context.Values.envGroups $groupName -}}
{{- range $key, $value := (index $context.Values.envGroups $groupName) }}
{{ $key }}="{{ $value }}"
{{- end -}}
{{- end -}}
{{- end -}}

{{/*
Helper to generate environment variables for multiple groups
*/}}
{{- define "restarters.envGroups" -}}
{{- $groupNames := .groupNames -}}
{{- $context := .context -}}
{{- range $groupName := $groupNames -}}
{{- if hasKey $context.Values.envGroups $groupName -}}
{{- range $key, $value := (index $context.Values.envGroups $groupName) }}
{{ $key }}="{{ $value }}"
{{- end -}}
{{- end -}}
{{- end -}}
{{- end -}}
