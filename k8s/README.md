# Kubernetes Deployment Notes

Folder ini berisi manifest Kubernetes untuk Smart Air Quality Monitoring.

## Apply Manifest

```bash
kubectl apply -f k8s/
kubectl get pods -n smartcity
kubectl get svc -n smartcity
kubectl get ingress -n smartcity
kubectl get hpa -n smartcity
```

## Local Ingress Hosts

Untuk demo lokal, arahkan host berikut ke IP ingress controller.

```text
smartcity.local
node-red.smartcity.local
grafana.smartcity.local
prometheus.smartcity.local
```

Contoh jika memakai Minikube:

```bash
minikube ip
```

Lalu tambahkan hasil IP tersebut ke file hosts.

## Docker Images

Manifest memakai default image GHCR berikut:

```text
ghcr.io/ainaa0099/smartcity-api-gateway:latest
ghcr.io/ainaa0099/smartcity-oauth-server:latest
ghcr.io/ainaa0099/smartcity-citizen-service:latest
ghcr.io/ainaa0099/smartcity-airquality-service:latest
ghcr.io/ainaa0099/smartcity-environment-service:latest
ghcr.io/ainaa0099/smartcity-python-ml:latest
ghcr.io/ainaa0099/smartcity-iot-simulator:latest
```

Jika anggota Docker memakai registry atau tag berbeda, ganti field `image:` pada file deployment terkait.

Image lokal yang sudah berhasil dibuild:

```bash
docker build -t ghcr.io/ainaa0099/smartcity-api-gateway:latest gateway/
docker build -t ghcr.io/ainaa0099/smartcity-oauth-server:latest services/auth/
docker build -t ghcr.io/ainaa0099/smartcity-citizen-service:latest php-citizen/
docker build -t ghcr.io/ainaa0099/smartcity-airquality-service:latest air-quality-service/
docker build -t ghcr.io/ainaa0099/smartcity-environment-service:latest environment-service/
docker build -t ghcr.io/ainaa0099/smartcity-python-ml:latest ml-service/
docker build -t ghcr.io/ainaa0099/smartcity-iot-simulator:latest ml-service/
```

Setelah login GHCR, push image:

```bash
docker push ghcr.io/ainaa0099/smartcity-api-gateway:latest
docker push ghcr.io/ainaa0099/smartcity-oauth-server:latest
docker push ghcr.io/ainaa0099/smartcity-citizen-service:latest
docker push ghcr.io/ainaa0099/smartcity-airquality-service:latest
docker push ghcr.io/ainaa0099/smartcity-environment-service:latest
docker push ghcr.io/ainaa0099/smartcity-python-ml:latest
docker push ghcr.io/ainaa0099/smartcity-iot-simulator:latest
```

## Secrets

`secrets.yaml` menggunakan nilai dari `.env.example` agar aman dipush ke GitHub. Jangan commit secret production asli.

Encode value baru ke base64:

```powershell
[Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes("nilai_secret"))
```

## Node-RED Flow

`rabbitmq-deployment.yaml` sudah menyertakan ConfigMap `node-red-flows` dari `node-red-data/flows.json` pada zip ML/IoT simulator. Init container menyalin flow ke:

```text
/data/flows.json
```

Jika flow berubah, ganti isi `data.flows.json` pada ConfigMap `node-red-flows`.

## Python ML Model

Deployment Python ML mengharapkan model tersedia di dalam image pada:

```text
/app/models/smartcity_models.pkl
```

Jika path di kode ML berbeda, sesuaikan env `MODEL_PATH` pada `python-ml-deployment.yaml`.

Model sudah digenerate dari `ml-service/train_models.py` dan diverifikasi bisa dibaca di image `ghcr.io/ainaa0099/smartcity-python-ml:latest`.
