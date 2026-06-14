import pandas as pd
import numpy as np
import pickle
import os
from sklearn.model_selection import train_test_split
from sklearn.preprocessing import StandardScaler
from sklearn.ensemble import RandomForestClassifier, RandomForestRegressor, IsolationForest
from sklearn.metrics import classification_report, mean_absolute_error

BASE_DIR = os.path.dirname(os.path.abspath(__file__))
CSV_PATH = os.path.join(BASE_DIR, 'data', 'smartcity_telemetry.csv')
MODEL_DIR = os.path.join(BASE_DIR, 'models')
MODEL_PATH = os.path.join(MODEL_DIR, 'smartcity_models.pkl')

def create_targets_and_train():
    if not os.path.exists(CSV_PATH):
        raise FileNotFoundError(f"File data tidak ditemukan di {CSV_PATH}. Jalankan generate_dataset.py terlebih dahulu.")
        
    df = pd.read_csv(CSV_PATH)
    
    # Klasifikasi internal AQI berdasarkan ambang batas PM2.5
    def assign_aqi_class(pm):
        if pm <= 35: return 0    # Baik
        elif pm <= 75: return 1  # Sedang
        else: return 2           # Tidak Sehat
        
    df['aqi_class'] = df['pm25'].apply(assign_aqi_class)
    
    # Fitur prediktor cuaca dan gas sekunder
    feature_cols = ['no2', 'co', 'o3', 'temperature', 'humidity', 'wind_speed', 'wind_direction']
    
    # 1. Training AQI Classifier
    X_clf = df[feature_cols]
    y_clf = df['aqi_class']
    X_train_c, X_test_c, y_train_c, y_test_c = train_test_split(X_clf, y_clf, test_size=0.2, random_state=42)
    
    scaler = StandardScaler()
    X_train_c_scaled = scaler.fit_transform(X_train_c)
    X_test_c_scaled = scaler.transform(X_test_c)
    
    clf_model = RandomForestClassifier(n_estimators=100, random_state=42)
    clf_model.fit(X_train_c_scaled, y_train_c)
    print("Evaluasi AQI Classifier:")
    print(classification_report(y_test_c, clf_model.predict(X_test_c_scaled)))
    
    # 2. Training Pollution Predictor (Regression)
    y_reg = df['pm25']
    X_train_r, X_test_r, y_train_r, y_test_r = train_test_split(X_clf, y_reg, test_size=0.2, random_state=42)
    
    reg_model = RandomForestRegressor(n_estimators=100, random_state=42)
    reg_model.fit(scaler.transform(X_train_r), y_train_r)
    reg_preds = reg_model.predict(scaler.transform(X_test_r))
    print(f"Pollution Predictor MAE: {mean_absolute_error(y_test_r, reg_preds):.2f} unit PM2.5\n")
    
    # 3. Training Anomaly Detector (Unsupervised)
    anomaly_features = ['pm25', 'pm10', 'no2', 'co', 'o3', 'temperature', 'humidity', 'wind_speed']
    X_anom = df[anomaly_features]
    
    anom_detector = IsolationForest(contamination=0.05, random_state=42)
    anom_detector.fit(X_anom)
    print("Anomaly Detector berhasil dilatih.")
    
    # Ekspor seluruh objek model
    models_payload = {
        'scaler': scaler,
        'feature_cols': feature_cols,
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