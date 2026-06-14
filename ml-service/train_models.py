import pandas as pd
import numpy as np
import pickle
import os
from sklearn.model_selection import train_test_split
from sklearn.preprocessing import StandardScaler
from sklearn.ensemble import RandomForestClassifier, RandomForestRegressor, IsolationForest
from sklearn.metrics import classification_report, mean_absolute_error

def create_targets_and_train():
    if not os.path.exists('data/smartcity_telemetry.csv'):
        raise FileNotFoundError("Please run generate_dataset.py first to create the telemetry data.")
        
    df = pd.read_csv('data/smartcity_telemetry.csv')
    
    # --- 1. DEFINE TARGETS FOR THE SEPARATE TASKS ---
    # AQI Classifier Target: Categorize based on PM2.5 breaks
    def assign_aqi_class(pm):
        if pm <= 35: return 0    # Good
        elif pm <= 75: return 1  # Moderate
        else: return 2           # Unhealthy
        
    df['aqi_class'] = df['pm25'].apply(assign_aqi_class)
    
    # Features common across models
    feature_cols = ['no2', 'co', 'o3', 'temperature', 'humidity', 'wind_speed', 'wind_direction']
    
    # --- 2. TRAIN AQI CLASSIFIER ---
    # Predicts AQI category using secondary gases and weather
    X_clf = df[feature_cols]
    y_clf = df['aqi_class']
    X_train_c, X_test_c, y_train_c, y_test_c = train_test_split(X_clf, y_clf, test_size=0.2, random_state=42)
    
    scaler = StandardScaler()
    X_train_c_scaled = scaler.fit_transform(X_train_c)
    X_test_c_scaled = scaler.transform(X_test_c)
    
    clf_model = RandomForestClassifier(n_estimators=100, random_state=42)
    clf_model.fit(X_train_c_scaled, y_train_c)
    print("AQI Classifier Evaluation:")
    print(classification_report(y_test_c, clf_model.predict(X_test_c_scaled)))
    
    # --- 3. TRAIN POLLUTION PREDICTOR (REGRESSOR) ---
    # Predicts actual numerical PM2.5 level
    y_reg = df['pm25']
    X_train_r, X_test_r, y_train_r, y_test_r = train_test_split(X_clf, y_reg, test_size=0.2, random_state=42)
    
    reg_model = RandomForestRegressor(n_estimators=100, random_state=42)
    reg_model.fit(scaler.transform(X_train_r), y_train_r)
    reg_preds = reg_model.predict(scaler.transform(X_test_r))
    print(f"Pollution Predictor MAE: {mean_absolute_error(y_test_r, reg_preds):.2f} PM2.5 units\n")
    
    # --- 4. TRAIN ANOMALY DETECTOR (UNSUPERVISED) ---
    # Uses all numerical indicators to isolate anomalous sensor drops or extreme spikes
    anomaly_features = ['pm25', 'pm10', 'no2', 'co', 'o3', 'temperature', 'humidity', 'wind_speed']
    X_anom = df[anomaly_features]
    
    # Contamination set roughly to matches injected anomalies (~5%)
    anom_detector = IsolationForest(contamination=0.05, random_state=42)
    anom_detector.fit(X_anom)
    
    # --- 5. BUNDLE AND EXPORT ---
    models_payload = {
        'scaler': scaler,
        'feature_cols': feature_cols,
        'anomaly_features': anomaly_features,
        'aqi_classifier': clf_model,
        'pollution_predictor': reg_model,
        'anomaly_detector': anom_detector
    }
    
    os.makedirs('models', exist_ok=True)
    with open('models/smartcity_models.pkl', 'wb') as f:
        pickle.dump(models_payload, f)
        
    print("All 3 models trained and saved successfully into 'models/smartcity_models.pkl'")

if __name__ == '__main__':
    create_targets_and_train()