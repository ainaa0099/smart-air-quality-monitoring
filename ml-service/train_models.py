import pandas as pd
import numpy as np
import pickle
import os
from sklearn.model_selection import train_test_split
from sklearn.preprocessing import StandardScaler
from sklearn.ensemble import GradientBoostingClassifier, RandomForestRegressor, IsolationForest
from sklearn.metrics import classification_report, mean_absolute_error

BASE_DIR = os.path.dirname(os.path.abspath(__file__))
CSV_PATH = os.path.join(BASE_DIR, 'data', 'smartcity_telemetry.csv')
MODEL_DIR = os.path.join(BASE_DIR, 'models')
MODEL_PATH = os.path.join(MODEL_DIR, 'smartcity_models.pkl')

def create_targets_and_train():
    if not os.path.exists(CSV_PATH):
        raise FileNotFoundError(f"File data tidak ditemukan di {CSV_PATH}. Jalankan generate_dataset.py terlebih dahulu.")
        
    df = pd.read_csv(CSV_PATH)

    df['future_pm25'] = (
    df.groupby('zone_id')['pm25']
      .shift(-1)
    )

    df = df.dropna(subset=['future_pm25'])
    
    # 1. Mengurutkan data berdasarkan Zona dan Waktu demi integritas Time-Series
    df['recorded_at'] = pd.to_datetime(df['recorded_at'])
    df = df.sort_values(by=['zone_id', 'recorded_at']).reset_index(drop=True)
    
    # 2. Ekstraksi Fitur Berbasis Waktu (Untuk Model 2 & 3)
    df['hour'] = df['recorded_at'].dt.hour
    df['day'] = df['recorded_at'].dt.weekday
    
    # 3. Fitur Spasio-Temporal (WAJIB dikelompokkan per Zone ID agar tidak bocor)
    # PM2.5 Sebelumnya (Lag 1)
    df['pm25_prev'] = df.groupby('zone_id')['pm25'].shift(1)
    df['pm25_prev'] = df['pm25_prev'].fillna(df.groupby('zone_id')['pm25'].transform('mean'))
    
    # Nilai Sensor Utama untuk Anomaly Detector
    df['sensor_value'] = df['pm25']
    
    # Rolling Mean 1 Jam (Menggunakan windowing berbasis data per zona)
    df['rolling_mean_1h'] = df.groupby('zone_id')['pm25'].transform(lambda x: x.rolling(window=2, min_periods=1).mean())
    
    # Z-Score polutan lokal per zona masing-masing
    def compute_z_score(group):
        std = group.std()
        if std == 0 or np.isnan(std):
            return np.zeros(len(group))
        return (group - group.mean()) / std

    df['z_score'] = df.groupby('zone_id')['pm25'].transform(compute_z_score)

    # --- MODEL 1: AQI CLASSIFIER (Gradient Boosting - 5 Kategori Resmi) ---
    def assign_aqi_class(pm):
        if pm <= 15: return 0      # Baik
        elif pm <= 55: return 1    # Sedang
        elif pm <= 150: return 2   # Tidak Sehat
        elif pm <= 250: return 3   # Sangat Tidak Sehat
        else: return 4             # Berbahaya
        
    df['aqi_class'] = df['pm25'].apply(assign_aqi_class)
    
    clf_features = ['pm25', 'pm10', 'no2', 'co', 'o3', 'temperature', 'humidity']
    X_clf = df[clf_features]
    y_clf = df['aqi_class']
    
    X_train_c, X_test_c, y_train_c, y_test_c = train_test_split(X_clf, y_clf, test_size=0.2, random_state=42)
    
    scaler_clf = StandardScaler()
    X_train_c_scaled = scaler_clf.fit_transform(X_train_c)
    X_test_c_scaled = scaler_clf.transform(X_test_c)
    
    clf_model = GradientBoostingClassifier(n_estimators=100, random_state=42)
    clf_model.fit(X_train_c_scaled, y_train_c)
    
    print("=== EVALUASI AQI CLASSIFIER (GRADIENT BOOSTING) ===")
    print(classification_report(y_test_c, clf_model.predict(X_test_c_scaled), zero_division=0))
    
    # --- MODEL 2: POLLUTION PREDICTOR (Random Forest Regressor) ---
    reg_features = ['hour', 'day', 'wind_speed', 'wind_direction', 'temperature', 'humidity', 'pm25_prev']
    X_reg = df[reg_features]
    y_reg = df['future_pm25']
    
    X_train_r, X_test_r, y_train_r, y_test_r = train_test_split(X_reg, y_reg, test_size=0.2, random_state=42)
    
    scaler_reg = StandardScaler()
    X_train_r_scaled = scaler_reg.fit_transform(X_train_r)
    X_test_r_scaled = scaler_reg.transform(X_test_r)
    
    reg_model = RandomForestRegressor(n_estimators=100, random_state=42)
    reg_model.fit(X_train_r_scaled, y_train_r)
    reg_preds = reg_model.predict(X_test_r_scaled)
    
    print("=== EVALUASI POLLUTION PREDICTOR (RANDOM FOREST REGRESSOR) ===")
    print(f"Pollution Predictor MAE: {mean_absolute_error(y_test_r, reg_preds):.2f} unit PM2.5\n")
    
    # --- MODEL 3: ANOMALY DETECTOR (Isolation Forest - Unsupervised) ---
    anomaly_features = ['sensor_value', 'hour', 'rolling_mean_1h', 'z_score']
    X_anom = df[anomaly_features]
    
    anom_detector = IsolationForest(contamination=0.05, random_state=42)
    anom_detector.fit(X_anom)
    print("=== EVALUASI ANOMALY DETECTOR ===")
    print("Anomaly Detector (Isolation Forest) sukses dilatih pada matriks statistik.\n")
    
    # --- EXPORT ARTIFACTS ---
    models_payload = {
        'scaler_clf': scaler_clf,
        'scaler_reg': scaler_reg,
        'clf_features': clf_features,
        'reg_features': reg_features,
        'anomaly_features': anomaly_features,
        'aqi_classifier': clf_model,
        'pollution_predictor': reg_model,
        'anomaly_detector': anom_detector
    }
    
    os.makedirs(MODEL_DIR, exist_ok=True)
    with open(MODEL_PATH, 'wb') as f:
        pickle.dump(models_payload, f)
        
    print(f"Seluruh model berhasil disimpan pada: {MODEL_PATH}")

if __name__ == '__main__':
    create_targets_and_train()