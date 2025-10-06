import React, { useEffect, useState } from "react";
import { Loading } from "/imports/ui/components/Loading";
import { useNavigate } from "react-router-dom";
import ImageUploader from "./ImageUploader";
import { LogoHeader } from "./LogoHeader";
import CompanyService from "../../../service/companyService";
import AuthService from "../../../service/authService";
import UserService from "../../../service/userService";

export const Header = () => {
  const navigate = useNavigate();
  const [loading, setLoading] = useState(true);
  const [logo, setLogo] = useState();
  const [role, setRole] = useState("ROLE_USER");

  useEffect(() => {
    getLogo();
  }, []);

  const getLogo = async () => {
    try {
      setLoading(true);
      const getLogo = await CompanyService.getLogo();
      setLogo(getLogo ?? "");
      const role = AuthService.getRole();
      setRole(role);
      setLoading(false);
    } catch (error) { }
  };

  return (
    <>
      {loading ? (
        <header className="headerMain">
          <div className="header h-[65px]"></div>
        </header>
      ) : (
        <header className="headerMain">
          <div className="header">
            <img className="logo" src="/images/general/logo.png" />
            <LogoHeader logo={logo} role={role} />
          </div>
        </header>
      )}
    </>
  );
};
