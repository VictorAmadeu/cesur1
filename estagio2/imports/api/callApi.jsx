import React from "react";
import { Meteor } from "meteor/meteor";

const baseUrl = Meteor.settings.public.baseUrl;

const sendRequest = async (url, data, token) => {
  const headers = new Headers({
    'Content-Type': 'application/json',
    ...(token && { 'Authorization': `Bearer ${token}` }) // Añade el token solo si está presente
  });

  const requestOptions = {
    method: 'POST',
    mode: 'cors',
    cache: 'no-cache',
    redirect: 'follow',
    referrerPolicy: 'no-referrer',
    headers: headers,
    body: JSON.stringify(data),
  };

  try {
    const response = await fetch(url, requestOptions);
    const text = await response.text();
    return text ? JSON.parse(text) : {};
  } catch (error) {
    console.error("Error in sendRequest:", error);
    throw error;
  }
};


export const callApi = async (point, param, token) => {
  const url = baseUrl + point;
  try {
    return await sendRequest(url, param, token);
  } catch (error) {
    throw error;
  }
};

export const keepAlive = async (point, param, token) => {
  const url = baseUrl + point;
  try {
    return await sendRequest(url, param, token);
  } catch (error) {
    console.error("Error in keepAlive:", error);
    return null;
  }
};

export const sendFormData = async (point, formData, token) => {
  const url = baseUrl + point;
  const headers = new Headers({
    'Authorization': `Bearer ${token}`
  });

  const requestOptions = {
    method: 'POST',
    mode: 'cors',
    cache: 'no-cache',
    redirect: 'follow',
    referrerPolicy: 'no-referrer',
    headers: headers,
    body: formData, // No es necesario convertir a JSON
  };

  try {
    const response = await fetch(url, requestOptions);
    return response.json();
  } catch (error) {
    console.error("Error in sendFormData:", error);
    throw error;
  }
};

